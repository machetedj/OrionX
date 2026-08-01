<?php
declare(strict_types=1);
namespace App\Repositories;

use PDO;
use RuntimeException;

final readonly class AccountRepository
{
    public function __construct(private PDO $db){}
    public function all(array $filters=[]): array
    {
        $where=['a.deleted_at IS NULL'];$params=[];$q=trim((string)($filters['q']??''));if($q!==''){$where[]='(a.username LIKE ? OR a.allowed_ip LIKE ? OR a.notes LIKE ?)';$term='%'.$q.'%';array_push($params,$term,$term,$term);}$status=(string)($filters['status']??'');if(in_array($status,['active','suspended','expired','blocked'],true)){$where[]='a.status=?';$params[]=$status;}$online=(string)($filters['online']??'');if($online==='yes')$where[]='EXISTS(SELECT 1 FROM active_sessions sx WHERE sx.account_id=a.id AND sx.disconnected_at IS NULL)';if($online==='no')$where[]='NOT EXISTS(SELECT 1 FROM active_sessions sx WHERE sx.account_id=a.id AND sx.disconnected_at IS NULL)';$owner=(int)($filters['owner']??0);if($owner>0){$where[]='a.reseller_id=?';$params[]=$owner;}$condition=implode(' AND ',$where);$count=$this->db->prepare('SELECT COUNT(*) FROM end_user_accounts a WHERE '.$condition);$count->execute($params);$total=(int)$count->fetchColumn();$per=in_array((int)($filters['per_page']??50),[25,50,100],true)?(int)$filters['per_page']:50;$pages=max(1,(int)ceil($total/$per));$page=min($pages,max(1,(int)($filters['page']??1)));$offset=($page-1)*$per;
        $sql="SELECT a.id,a.username,a.status,a.expires_at,a.max_connections,a.allowed_ip,a.allowed_country,a.notes,a.created_at,u.username owner_name,GROUP_CONCAT(DISTINCT p.name ORDER BY p.name SEPARATOR ', ') packages,(SELECT COUNT(*) FROM active_sessions s WHERE s.account_id=a.id AND s.disconnected_at IS NULL) active_sessions,(SELECT MAX(s.last_seen_at) FROM active_sessions s WHERE s.account_id=a.id) last_connection,(SELECT ci.title FROM active_sessions s LEFT JOIN content_items ci ON ci.id=s.content_item_id WHERE s.account_id=a.id AND s.disconnected_at IS NULL ORDER BY s.last_seen_at DESC LIMIT 1) watching_title,(SELECT ci.type FROM active_sessions s LEFT JOIN content_items ci ON ci.id=s.content_item_id WHERE s.account_id=a.id AND s.disconnected_at IS NULL ORDER BY s.last_seen_at DESC LIMIT 1) watching_type FROM end_user_accounts a LEFT JOIN resellers r ON r.id=a.reseller_id LEFT JOIN users u ON u.id=r.user_id LEFT JOIN account_packages ap ON ap.account_id=a.id LEFT JOIN packages p ON p.id=ap.package_id WHERE {$condition} GROUP BY a.id ORDER BY a.id DESC LIMIT {$per} OFFSET {$offset}";$statement=$this->db->prepare($sql);$statement->execute($params);return ['items'=>$statement->fetchAll(),'pagination'=>['page'=>$page,'pages'=>$pages,'per_page'=>$per,'total'=>$total]];
    }
    public function summary():array{return $this->db->query("SELECT COUNT(*) total,SUM(status='active' AND (expires_at IS NULL OR expires_at>NOW())) active,SUM(status='suspended') suspended,SUM(status='blocked') blocked,SUM(status='expired' OR expires_at<=NOW()) expired,(SELECT COUNT(DISTINCT account_id) FROM active_sessions WHERE disconnected_at IS NULL) online,(SELECT COUNT(*) FROM active_sessions WHERE disconnected_at IS NULL) connections FROM end_user_accounts WHERE deleted_at IS NULL")->fetch()?:[];}
    public function owners():array{return $this->db->query('SELECT r.id,u.username FROM resellers r JOIN users u ON u.id=r.user_id ORDER BY u.username')->fetchAll();}
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
