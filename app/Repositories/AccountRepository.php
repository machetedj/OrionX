<?php
declare(strict_types=1);
namespace App\Repositories;

use PDO;
use RuntimeException;

final readonly class AccountRepository
{
    public function __construct(private PDO $db){}
    public function all(): array
    {
        return $this->db->query("SELECT a.id,a.username,a.status,a.expires_at,a.max_connections,a.allowed_ip,a.allowed_country,a.notes,a.created_at,GROUP_CONCAT(p.name ORDER BY p.name SEPARATOR ', ') packages,(SELECT COUNT(*) FROM active_sessions s WHERE s.account_id=a.id AND s.disconnected_at IS NULL) active_sessions FROM end_user_accounts a LEFT JOIN account_packages ap ON ap.account_id=a.id LEFT JOIN packages p ON p.id=ap.package_id WHERE a.deleted_at IS NULL GROUP BY a.id ORDER BY a.id DESC LIMIT 500")->fetchAll();
    }
    public function packages(): array { return $this->db->query('SELECT id,name,duration_days,credit_cost FROM packages WHERE active=1 ORDER BY name')->fetchAll(); }
    public function find(int $id): ?array { $s=$this->db->prepare('SELECT * FROM end_user_accounts WHERE id=? AND deleted_at IS NULL');$s->execute([$id]);return $s->fetch()?:null; }
    public function create(array $data,array $packageIds,int $actorId): int
    {
        $s=$this->db->prepare('INSERT INTO end_user_accounts(reseller_id,username,credential_ciphertext,status,expires_at,max_connections,allowed_ip,allowed_country,allowed_user_agent,notes) VALUES(:reseller,:username,:credential,:status,:expires,:connections,:ip,:country,:agent,:notes)');
        $s->execute($data);$id=(int)$this->db->lastInsertId();
        $this->syncPackages($id,$packageIds);
        $this->history($id,$actorId,'created',null,array_diff_key($data,['credential'=>true]));
        return $id;
    }
    public function setStatus(int $id,string $status,int $actorId): void
    {
        $before=$this->find($id)??throw new RuntimeException('Cuenta no encontrada.');
        $this->db->prepare('UPDATE end_user_accounts SET status=? WHERE id=? AND deleted_at IS NULL')->execute([$status,$id]);
        $this->history($id,$actorId,'status_changed',['status'=>$before['status']],['status'=>$status]);
    }
    public function renew(int $id,int $days,int $actorId): void
    {
        $before=$this->find($id)??throw new RuntimeException('Cuenta no encontrada.');
        $s=$this->db->prepare("UPDATE end_user_accounts SET expires_at=DATE_ADD(GREATEST(COALESCE(expires_at,NOW()),NOW()),INTERVAL ? DAY),status=IF(status='expired','active',status) WHERE id=?");$s->execute([$days,$id]);
        $after=$this->find($id);$this->history($id,$actorId,'renewed',['expires_at'=>$before['expires_at']],['expires_at'=>$after['expires_at'],'days'=>$days]);
    }
    public function disconnect(int $id,string $reason): int { $s=$this->db->prepare('UPDATE active_sessions SET disconnected_at=NOW(),disconnect_reason=? WHERE account_id=? AND disconnected_at IS NULL');$s->execute([$reason,$id]);return $s->rowCount(); }
    public function softDelete(int $id,int $actorId): void { $this->db->prepare("UPDATE end_user_accounts SET deleted_at=NOW(),status='blocked' WHERE id=? AND deleted_at IS NULL")->execute([$id]);$this->disconnect($id,'account_deleted');$this->history($id,$actorId,'deleted',null,null); }
    private function syncPackages(int $accountId,array $packageIds): void { $s=$this->db->prepare('INSERT IGNORE INTO account_packages(account_id,package_id) VALUES(?,?)');foreach(array_unique(array_map('intval',$packageIds)) as $id)if($id>0)$s->execute([$accountId,$id]); }
    private function history(int $id,int $actorId,string $action,?array $before,?array $after): void { $s=$this->db->prepare('INSERT INTO account_history(account_id,actor_user_id,action,before_data,after_data) VALUES(?,?,?,?,?)');$s->execute([$id,$actorId,$action,$before?json_encode($before,JSON_THROW_ON_ERROR):null,$after?json_encode($after,JSON_THROW_ON_ERROR):null]); }
}
