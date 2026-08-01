<?php
declare(strict_types=1);
namespace App\Repositories;
use PDO;
final readonly class CertificateRepository
{
 public function __construct(private PDO $db){}
 public function all():array{return $this->db->query("SELECT c.*,s.name server_name,s.public_ip FROM certificate_requests c LEFT JOIN servers s ON s.id=c.server_id ORDER BY c.id DESC LIMIT 200")->fetchAll();}
 public function servers():array{return $this->db->query("SELECT id,name,public_ip,status FROM servers ORDER BY name")->fetchAll();}
 public function pendingForNode(int $serverId):?array{$s=$this->db->prepare("SELECT id,domain,email FROM certificate_requests WHERE server_id=? AND target_type='balancer' AND status IN ('queued','waiting_node') ORDER BY id LIMIT 1");$s->execute([$serverId]);return $s->fetch()?:null;}
}
