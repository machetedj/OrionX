<?php
declare(strict_types=1);
namespace App\Repositories;

use PDO;

final readonly class ResellerRepository
{
    public function __construct(private PDO $db){}
    public function byUser(int $userId): ?array { $s=$this->db->prepare('SELECT r.*,u.name,u.email FROM resellers r JOIN users u ON u.id=r.user_id WHERE r.user_id=? AND r.active=1');$s->execute([$userId]);return $s->fetch()?:null; }
    public function accounts(int $id): array { $s=$this->db->prepare('SELECT id,username,status,expires_at,max_connections,created_at FROM end_user_accounts WHERE reseller_id=? AND deleted_at IS NULL ORDER BY id DESC LIMIT 300');$s->execute([$id]);return $s->fetchAll(); }
    public function movements(int $id): array { $s=$this->db->prepare('SELECT amount,balance_before,balance_after,reason,ip,uuid,created_at FROM reseller_credit_transactions WHERE reseller_id=? ORDER BY id DESC LIMIT 100');$s->execute([$id]);return $s->fetchAll(); }
    public function packages(int $id): array { $s=$this->db->prepare('SELECT p.id,p.name,p.duration_days,rp.internal_price,rp.max_duration_days,rp.max_connections FROM reseller_packages rp JOIN packages p ON p.id=rp.package_id WHERE rp.reseller_id=? AND rp.active=1 AND p.active=1 ORDER BY p.name');$s->execute([$id]);return $s->fetchAll(); }
    public function summary(int $id):array{$s=$this->db->prepare("SELECT COUNT(*) total,SUM(status='active' AND (expires_at IS NULL OR expires_at>NOW())) active,SUM(status='expired' OR expires_at<=NOW()) expired,SUM(is_trial=1) trials,(SELECT COUNT(DISTINCT a2.id) FROM end_user_accounts a2 JOIN active_sessions ses ON ses.account_id=a2.id AND ses.disconnected_at IS NULL WHERE a2.reseller_id=?) online FROM end_user_accounts a WHERE a.reseller_id=? AND a.deleted_at IS NULL");$s->execute([$id,$id]);return $s->fetch()?:[];}
}
