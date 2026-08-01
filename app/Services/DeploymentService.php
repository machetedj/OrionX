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
