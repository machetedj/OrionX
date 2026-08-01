<?php
declare(strict_types=1);
namespace App\Repositories;
use PDO;
final readonly class DeploymentRepository
{
 public function __construct(private PDO $db){}
 public function servers():array{return $this->db->query("SELECT s.id,s.name,s.public_ip,s.private_ip,s.region,s.status,s.installed_version,s.legacy_origin,s.cutover_authorized_at,CASE WHEN c.server_id IS NULL THEN 0 ELSE 1 END ssh_configured,c.ssh_port,c.ssh_user,c.host_fingerprint FROM servers s LEFT JOIN server_ssh_credentials c ON c.server_id=s.id ORDER BY s.id DESC")->fetchAll();}
 public function history():array{return $this->db->query('SELECT d.*,s.name server_name FROM server_deployments d JOIN servers s ON s.id=d.server_id ORDER BY d.id DESC LIMIT 100')->fetchAll();}
}
