<?php
declare(strict_types=1);namespace App\Repositories;use PDO;
final readonly class DatabaseFirewallRepository{
 public function __construct(private PDO $db){}
 public function rules():array{return $this->db->query("SELECT a.*,s.name server_name,s.status server_status,u.username actor FROM database_ip_allowlist a JOIN servers s ON s.id=a.server_id LEFT JOIN users u ON u.id=a.created_by ORDER BY a.active DESC,s.name,a.ip_kind")->fetchAll();}
 public function servers():array{return $this->db->query("SELECT id,name,public_ip,private_ip,status FROM servers WHERE public_ip IS NOT NULL OR private_ip IS NOT NULL ORDER BY name")->fetchAll();}
 public function runs():array{return $this->db->query('SELECT r.*,u.username actor FROM database_firewall_runs r LEFT JOIN users u ON u.id=r.requested_by ORDER BY r.id DESC LIMIT 20')->fetchAll();}
}
