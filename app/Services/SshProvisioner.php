<?php
declare(strict_types=1);

namespace App\Services;

use App\Security\DeviceCredentialCipher;
use PDO;
use phpseclib3\Crypt\PublicKeyLoader;
use phpseclib3\Net\SFTP;
use phpseclib3\Net\SSH2;
use RuntimeException;
use Throwable;

final readonly class SshProvisioner
{
    private const REMOTE_DIRECTORY = '/tmp/media-panel-deploy';

    public function __construct(private PDO $db, private DeviceCredentialCipher $cipher, private BalancerBootstrap $bootstrap) {}

    public function fingerprint(string $host, int $port = 22): string
    {
        $this->validateHost($host, $port);
        $ssh = new SSH2($host, $port, 10);
        $key = $ssh->getServerPublicHostKey();
        if (!$key) {
            throw new RuntimeException('No se pudo obtener la clave pública SSH.');
        }

        return (string) PublicKeyLoader::load($key)->getFingerprint('sha256');
    }

    public function deploy(int $deploymentId): array
    {
        $statement = $this->db->prepare(
            'SELECT d.*, c.ssh_host, c.ssh_port, c.ssh_user, c.host_fingerprint,
                    c.private_key_ciphertext, c.password_ciphertext,
                    s.api_key_id, s.api_secret_ciphertext, s.status AS server_status
             FROM server_deployments d
             JOIN server_ssh_credentials c ON c.server_id = d.server_id
             JOIN servers s ON s.id = d.server_id
             WHERE d.id = ?'
        );
        $statement->execute([$deploymentId]);
        $deployment = $statement->fetch() ?: throw new RuntimeException('Despliegue no encontrado.');
        $this->db->prepare("UPDATE server_deployments SET status='running', started_at=NOW(), error_message=NULL WHERE id=?")
            ->execute([$deploymentId]);
        try {
            $this->ensureNodeCredentials($deployment);
            $statement->execute([$deploymentId]);
            $deployment = $statement->fetch();
            $ssh = $this->ssh($deployment, SSH2::class);
            $sftp = $this->ssh($deployment, SFTP::class);
            $sftp->delete(self::REMOTE_DIRECTORY, true);
            if (!$sftp->mkdir(self::REMOTE_DIRECTORY, 0700, true)) {
                throw new RuntimeException('No se pudo crear el directorio temporal remoto.');
            }

            $assets = dirname(__DIR__, 2) . '/resources/balancer';
            $bundleFiles = [
                'agent.php' => $assets . '/agent.php',
                'media-task.php' => $assets . '/media-task.php',
                'rtmp-agent.php' => $assets . '/rtmp-agent.php',
                'rtmp-task.php' => $assets . '/rtmp-task.php',
                'media-balancer.service' => $assets . '/media-balancer.service',
                'media-balancer.timer' => $assets . '/media-balancer.timer',
                'nginx-balancer.conf' => $assets . '/nginx-balancer.conf',
            ];
            foreach ($bundleFiles as $file => $localPath) {
                if (!$sftp->put(self::REMOTE_DIRECTORY . '/' . $file, $localPath, SFTP::SOURCE_LOCAL_FILE)) {
                    throw new RuntimeException('No se pudo transferir ' . $file . '.');
                }
            }
            if (!$sftp->put(self::REMOTE_DIRECTORY . '/bootstrap', $this->bootstrap->render())) {
                throw new RuntimeException('No se pudo transferir el procedimiento de instalación.');
            }

            $mainUrl = rtrim((string) ($_ENV['APP_URL'] ?? ''), '/');
            if (!str_starts_with($mainUrl, 'https://')) {
                throw new RuntimeException('APP_URL debe usar HTTPS antes de instalar balanceadores.');
            }
            $agentEnvironment = sprintf(
                "MAIN_URL=\"%s\"\nNODE_KEY=\"%s\"\nNODE_SECRET=\"%s\"\nAGENT_VERSION=\"%s\"\nPHP_VERSION=\"%s\"\n",
                $mainUrl,
                $deployment['api_key_id'],
                $this->cipher->decrypt($deployment['api_secret_ciphertext']),
                $deployment['version'],
                (string) ($_ENV['BALANCER_PHP_VERSION'] ?? '8.5')
            );
            if (!$sftp->put(self::REMOTE_DIRECTORY . '/agent.env', $agentEnvironment)) {
                throw new RuntimeException('No se pudo transferir la configuración del agente.');
            }
            $sftp->chmod(0600, self::REMOTE_DIRECTORY . '/agent.env');

            $installCommand = $deployment['ssh_user'] === 'root'
                ? '/bin/bash /tmp/media-panel-deploy/bootstrap'
                : '/usr/bin/sudo -n /bin/bash /tmp/media-panel-deploy/bootstrap';
            $output = (string) $ssh->exec($installCommand);
            $exitCode = $ssh->getExitStatus();
            if ($exitCode === 0) {
                $verification = (string) $ssh->exec(
                    '/bin/systemctl is-active nginx redis-server media-balancer.timer && /usr/bin/php -r \"echo PHP_VERSION;\"'
                );
                $verificationExitCode = $ssh->getExitStatus();
                $output .= "\nVERIFY\n" . $verification;
                if ($verificationExitCode !== 0) {
                    $exitCode = $verificationExitCode;
                }
            }
            $sftp->delete(self::REMOTE_DIRECTORY, true);
            if ($exitCode !== 0 || !str_contains($output, 'BALANCER_INSTALL_OK')) {
                throw new RuntimeException('La instalación remota falló: ' . substr($output, 0, 1000));
            }

            $this->db->prepare("UPDATE server_deployments SET status='completed', output=?, completed_at=NOW() WHERE id=?")
                ->execute([substr($output, 0, 65535), $deploymentId]);
            $this->db->prepare("UPDATE servers SET installed_version=?, agent_status='ok', nginx_status='ok', redis_status='ok' WHERE id=?")
                ->execute([$deployment['version'], $deployment['server_id']]);
            return ['deployment_id' => $deploymentId, 'server_id' => (int) $deployment['server_id'], 'status' => 'completed'];
        } catch (Throwable $exception) {
            if (isset($sftp)) {
                $sftp->delete(self::REMOTE_DIRECTORY, true);
            }
            $this->db->prepare("UPDATE server_deployments SET status='failed', error_message=?, completed_at=NOW() WHERE id=?")
                ->execute([substr($exception->getMessage(), 0, 65535), $deploymentId]);
            throw $exception;
        }
    }

    public function mediaTask(int $serverId,array $task):array
    {
        $s=$this->db->prepare('SELECT c.ssh_host,c.ssh_port,c.ssh_user,c.host_fingerprint,c.private_key_ciphertext,c.password_ciphertext FROM server_ssh_credentials c WHERE c.server_id=?');$s->execute([$serverId]);$config=$s->fetch()?:throw new RuntimeException('Configura primero el acceso SSH del servidor.');
        $ssh=$this->ssh($config,SSH2::class);$sftp=$this->ssh($config,SFTP::class);$directory=self::REMOTE_DIRECTORY.'-media-'.bin2hex(random_bytes(6));
        try{if(!$sftp->mkdir($directory,0700,true))throw new RuntimeException('No se pudo preparar la tarea multimedia remota.');$script=dirname(__DIR__,2).'/resources/balancer/media-task.php';if(!$sftp->put($directory.'/media-task.php',$script,SFTP::SOURCE_LOCAL_FILE)||!$sftp->put($directory.'/task.json',json_encode($task,JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR)))throw new RuntimeException('No se pudo transferir la tarea multimedia.');$sftp->chmod(0700,$directory.'/media-task.php');$sftp->chmod(0600,$directory.'/task.json');$ssh->setTimeout(0);$output=(string)$ssh->exec('/usr/bin/php '.$directory.'/media-task.php '.$directory.'/task.json');$exit=$ssh->getExitStatus();if($exit!==0)throw new RuntimeException('La tarea multimedia remota falló: '.substr($output,0,1000));$result=json_decode($output,true,64,JSON_THROW_ON_ERROR);if(!is_array($result)||empty($result['ok']))throw new RuntimeException('Respuesta multimedia remota inválida.');return $result;}finally{$sftp->delete($directory,true);}
    }

    public function wafTask(int $serverId,string $config):string
    {
        $s=$this->db->prepare('SELECT c.ssh_host,c.ssh_port,c.ssh_user,c.host_fingerprint,c.private_key_ciphertext,c.password_ciphertext FROM server_ssh_credentials c WHERE c.server_id=?');$s->execute([$serverId]);$credentials=$s->fetch()?:throw new RuntimeException('Configura primero el acceso SSH del balanceador.');$ssh=$this->ssh($credentials,SSH2::class);$sftp=$this->ssh($credentials,SFTP::class);$directory=self::REMOTE_DIRECTORY.'-waf-'.bin2hex(random_bytes(6));
        try{if(!$sftp->mkdir($directory,0700,true))throw new RuntimeException('No se pudo preparar el despliegue WAF remoto.');$installer=dirname(__DIR__,2).'/resources/security/waf-install.sh';if(!$sftp->put($directory.'/install.sh',$installer,SFTP::SOURCE_LOCAL_FILE)||!$sftp->put($directory.'/orionx.conf',$config))throw new RuntimeException('No se pudo transferir la configuración WAF.');$sftp->chmod(0700,$directory.'/install.sh');$sftp->chmod(0600,$directory.'/orionx.conf');$command=$credentials['ssh_user']==='root'?'/bin/bash '.$directory.'/install.sh '.$directory.'/orionx.conf':'/usr/bin/sudo -n /bin/bash '.$directory.'/install.sh '.$directory.'/orionx.conf';$ssh->setTimeout(0);$output=(string)$ssh->exec($command);$exit=$ssh->getExitStatus();if($exit!==0||!str_contains($output,'WAF_DEPLOY_OK'))throw new RuntimeException('El despliegue WAF remoto falló: '.substr($output,0,2000));return trim($output);}finally{$sftp->delete($directory,true);}
    }

    private function ssh(array $config, string $class): SSH2|SFTP
    {
        $host = $config['ssh_host'];
        $port = (int) $config['ssh_port'];
        $this->validateHost($host, $port);
        $connection = new $class($host, $port, 15);
        $hostKey = $connection->getServerPublicHostKey();
        if (!$hostKey) {
            throw new RuntimeException('El servidor SSH no presentó una clave.');
        }
        $fingerprint = (string) PublicKeyLoader::load($hostKey)->getFingerprint('sha256');
        if (!hash_equals($config['host_fingerprint'], $fingerprint)) {
            throw new RuntimeException('La huella SSH cambió; despliegue bloqueado.');
        }
        $auth = $config['private_key_ciphertext']
            ? PublicKeyLoader::loadPrivateKey($this->cipher->decrypt($config['private_key_ciphertext']))
            : $this->cipher->decrypt((string) $config['password_ciphertext']);
        if (!$connection->login($config['ssh_user'], $auth)) {
            throw new RuntimeException('Autenticación SSH rechazada.');
        }
        return $connection;
    }

    private function ensureNodeCredentials(array $server): void
    {
        if ($server['api_key_id'] && $server['api_secret_ciphertext']) {
            return;
        }
        $key = bin2hex(random_bytes(8));
        $secret = rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
        $this->db->prepare('UPDATE servers SET api_key_id=?, api_secret_ciphertext=? WHERE id=?')
            ->execute([$key, $this->cipher->encrypt($secret), $server['server_id']]);
    }

    private function validateHost(string $host, int $port): void
    {
        if (!filter_var($host, FILTER_VALIDATE_IP) || $port < 1 || $port > 65535) {
            throw new RuntimeException('Destino SSH inválido.');
        }
    }
}
