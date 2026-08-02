<?php
declare(strict_types=1);

namespace App\Services;

use App\Repositories\AuditRepository;
use App\Security\Auth;
use App\Security\DeviceCredentialCipher;
use PDO;
use RuntimeException;

final readonly class DeploymentService
{
    public function __construct(
        private PDO $db,
        private DeviceCredentialCipher $cipher,
        private TaskQueueService $queue,
        private SshProvisioner $ssh,
        private AuditRepository $audit
    ) {}

    public function fingerprint(int $serverId, int $port): string
    {
        return $this->ssh->fingerprint($this->serverHost($serverId), $port);
    }

    public function fingerprintHost(string $host, int $port): string
    {
        if (!filter_var($host, FILTER_VALIDATE_IP) || $port < 1 || $port > 65535) {
            throw new RuntimeException('IP o puerto SSH inválido.');
        }
        return $this->ssh->fingerprint($host, $port);
    }

    public function installNew(array $input): int
    {
        $name = trim((string) ($input['name'] ?? ''));
        $host = trim((string) ($input['server_ip'] ?? ''));
        $port = (int) ($input['port'] ?? 35222);
        $user = trim((string) ($input['ssh_user'] ?? 'root'));
        $password = (string) ($input['ssh_password'] ?? '');
        $privateKey = trim((string) ($input['private_key'] ?? ''));
        $fingerprint = trim((string) ($input['fingerprint'] ?? ''));
        if ($name === '' || mb_strlen($name) > 120) throw new RuntimeException('Nombre del balanceador inválido.');
        if (!filter_var($host, FILTER_VALIDATE_IP)) throw new RuntimeException('IP del balanceador inválida.');
        if ($user !== 'root') throw new RuntimeException('La instalación inicial debe ejecutarse como root.');
        if ($password === '' && $privateKey === '') throw new RuntimeException('Proporciona la contraseña root o una clave privada.');
        $actualFingerprint = $this->fingerprintHost($host, $port);
        if ($fingerprint === '' || !hash_equals($actualFingerprint, $fingerprint)) throw new RuntimeException('Confirma primero la huella SSH del servidor.');
        $exists = $this->db->prepare('SELECT 1 FROM servers WHERE public_ip=? OR private_ip=?');
        $exists->execute([$host, $host]);
        if ($exists->fetchColumn()) throw new RuntimeException('Ya existe un servidor registrado con esa IP.');
        $this->db->prepare("INSERT INTO servers(name,public_ip,status,max_capacity,weight,priority) VALUES(?,?,'pending',1000,100,100)")->execute([$name, $host]);
        $id = (int) $this->db->lastInsertId();
        try {
            $this->saveCredentials($id, $port, $user, $fingerprint, $privateKey, $password);
            $this->request($id, 'install');
        } catch (\Throwable $e) {
            $this->db->prepare('DELETE FROM servers WHERE id=?')->execute([$id]);
            throw $e;
        }
        $this->audit->record('server.installation_started', 'server', $id, ['host'=>$host,'port'=>$port]);
        return $id;
    }

    public function saveCredentials(int $serverId, int $port, string $user, string $fingerprint, string $privateKey, string $password): void
    {
        $host = $this->serverHost($serverId);
        if (!preg_match('/^[a-z_][a-z0-9_-]{0,31}$/i', $user)) {
            throw new RuntimeException('Usuario SSH inválido.');
        }
        if ($privateKey === '' && $password === '') {
            throw new RuntimeException('Proporciona clave privada o contraseña SSH.');
        }
        if (!preg_match('/^[A-Za-z0-9:+\/=._-]{20,100}$/', $fingerprint)) {
            throw new RuntimeException('Huella SSH inválida.');
        }
        if (!hash_equals($fingerprint, $this->ssh->fingerprint($host, $port))) {
            throw new RuntimeException('La huella confirmada no coincide.');
        }

        $statement = $this->db->prepare(
            'INSERT INTO server_ssh_credentials(server_id,ssh_host,ssh_port,ssh_user,host_fingerprint,private_key_ciphertext,password_ciphertext,created_by)
             VALUES(?,?,?,?,?,?,?,?) ON DUPLICATE KEY UPDATE ssh_host=VALUES(ssh_host),ssh_port=VALUES(ssh_port),
             ssh_user=VALUES(ssh_user),host_fingerprint=VALUES(host_fingerprint),private_key_ciphertext=VALUES(private_key_ciphertext),
             password_ciphertext=VALUES(password_ciphertext),created_by=VALUES(created_by)'
        );
        $statement->execute([
            $serverId, $host, $port, $user, $fingerprint,
            $privateKey !== '' ? $this->cipher->encrypt($privateKey) : null,
            $password !== '' ? $this->cipher->encrypt($password) : null,
            Auth::id() ?? throw new RuntimeException('Sesión inválida.'),
        ]);
        $this->audit->record('server.ssh_credentials_updated', 'server', $serverId, ['host' => $host, 'port' => $port, 'user' => $user]);
    }

    public function request(int $serverId, string $type): int
    {
        $legacy = $this->db->prepare('SELECT legacy_origin,cutover_authorized_at FROM servers WHERE id=?');
        $legacy->execute([$serverId]);
        $legacy = $legacy->fetch();
        if ($legacy && $legacy['legacy_origin'] === 'xui_one' && $legacy['cutover_authorized_at'] === null) {
            throw new RuntimeException('Protección activa: este servidor todavía opera con XUI One. Usa un balanceador paralelo para evitar cortes.');
        }
        if (!in_array($type, ['install', 'sync', 'update'], true)) {
            throw new RuntimeException('Tipo de despliegue inválido.');
        }
        $exists = $this->db->prepare('SELECT 1 FROM server_ssh_credentials WHERE server_id=?');
        $exists->execute([$serverId]);
        if (!$exists->fetchColumn()) {
            throw new RuntimeException('Configura primero las credenciales SSH.');
        }
        $version = (string) ($_ENV['BALANCER_AGENT_VERSION'] ?? '1.0.0');
        $this->db->prepare('INSERT INTO server_deployments(server_id,type,version,requested_by) VALUES(?,?,?,?)')
            ->execute([$serverId, $type, $version, Auth::id() ?? 0]);
        $id = (int) $this->db->lastInsertId();
        $taskType = match ($type) {
            'install' => 'provision_balancer',
            'update' => 'update_balancer',
            default => 'sync_balancer',
        };
        $job = $this->queue->enqueue($taskType, ['deployment_id' => $id], 5, 'deployment-' . $id);
        $this->db->prepare('UPDATE server_deployments SET job_id=? WHERE id=?')->execute([$job, $id]);
        $this->audit->record('server.deployment_queued', 'server', $serverId, ['deployment_id' => $id, 'type' => $type]);
        return $id;
    }

    public function authorizeCutover(int $serverId): void
    {
        $statement=$this->db->prepare('SELECT legacy_origin,cutover_authorized_at FROM servers WHERE id=?');
        $statement->execute([$serverId]);$server=$statement->fetch();
        if(!$server||$server['legacy_origin']!=='xui_one')throw new RuntimeException('Este servidor no fue importado desde XUI One.');
        if($server['cutover_authorized_at']!==null)return;
        $statement=$this->db->prepare("SELECT r.id,r.status,(SELECT COUNT(*) FROM media_symlink_inventory i WHERE i.run_id=r.id AND i.valid=0) invalid_links,(SELECT COUNT(*) FROM media_symlink_inventory i WHERE i.run_id=r.id) total_links FROM media_remote_runs r WHERE r.server_id=? AND r.operation='inventory' ORDER BY r.id DESC LIMIT 1");
        $statement->execute([$serverId]);$inventory=$statement->fetch();
        if(!$inventory||$inventory['status']!=='completed')throw new RuntimeException('Ejecuta primero el inventario de enlaces desde Bibliotecas.');
        if((int)$inventory['invalid_links']>0)throw new RuntimeException('Hay enlaces fuera de las bibliotecas autorizadas. Corrígelos antes del reemplazo.');
        $statement=$this->db->prepare("SELECT COUNT(*) FROM media_remote_runs WHERE server_id=? AND status IN ('pending','running')");$statement->execute([$serverId]);
        if((int)$statement->fetchColumn()>0)throw new RuntimeException('Espera a que terminen las operaciones multimedia pendientes.');
        $this->db->prepare('UPDATE servers SET cutover_authorized_at=NOW() WHERE id=?')->execute([$serverId]);
        $this->audit->record('server.xui_cutover_authorized','server',$serverId,['inventory_run_id'=>(int)$inventory['id'],'links'=>(int)$inventory['total_links']]);
    }

    private function serverHost(int $id): string
    {
        $statement = $this->db->prepare("SELECT COALESCE(NULLIF(private_ip,''),public_ip) FROM servers WHERE id=?");
        $statement->execute([$id]);
        $host = $statement->fetchColumn();
        if (!$host || !filter_var($host, FILTER_VALIDATE_IP)) {
            throw new RuntimeException('El balanceador no tiene una IP válida registrada.');
        }
        return (string) $host;
    }
}
