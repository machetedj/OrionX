<?php
declare(strict_types=1);
namespace App\Repositories;
use PDO;
final readonly class ProxyRepository
{
 public function __construct(private PDO $db){}
 public function all():array{return $this->db->query("SELECT p.id,p.server_id,p.name,p.scheme,p.host,p.port,p.username,p.active,p.created_at,p.updated_at,s.name server_name FROM server_proxies p LEFT JOIN servers s ON s.id=p.server_id ORDER BY p.id DESC")->fetchAll();}
 public function servers():array{return $this->db->query("SELECT id,name,status,region FROM servers ORDER BY name")->fetchAll();}
}
