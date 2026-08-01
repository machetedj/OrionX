<?php
declare(strict_types=1);
namespace App\Repositories;
use PDO;
final readonly class IpBlockRepository
{
 public function __construct(private PDO $db){}
 public function blocks():array{return $this->db->query("SELECT b.*,u.username actor FROM blocked_ips b LEFT JOIN users u ON u.id=b.created_by ORDER BY b.active DESC,b.id DESC LIMIT 1000")->fetchAll();}
 public function suspects():array{return $this->db->query("SELECT ip,COUNT(*) events,MAX(created_at) last_event,GROUP_CONCAT(DISTINCT LEFT(message,80) SEPARATOR ' · ') reasons FROM system_logs WHERE ip IS NOT NULL AND category IN ('security','authentication','api') AND level IN ('warning','error','critical') AND created_at>=DATE_SUB(NOW(),INTERVAL 24 HOUR) AND ip NOT IN (SELECT ip FROM blocked_ips WHERE active=1 AND (expires_at IS NULL OR expires_at>NOW())) GROUP BY ip HAVING events>=3 ORDER BY events DESC LIMIT 100")->fetchAll();}
}
