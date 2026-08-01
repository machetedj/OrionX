<?php
declare(strict_types=1);
namespace App\Repositories;
use PDO;
final readonly class MagDeviceRepository
{
 public function __construct(private PDO $db){}
 public function all():array{return $this->db->query("SELECT m.id,m.account_id,m.status,m.last_ip,m.last_seen_at,m.created_at,a.username,a.expires_at FROM mag_devices m JOIN end_user_accounts a ON a.id=m.account_id ORDER BY m.id DESC LIMIT 500")->fetchAll();}
 public function accounts():array{return $this->db->query("SELECT id,username,status,expires_at FROM end_user_accounts WHERE deleted_at IS NULL ORDER BY username")->fetchAll();}
}
