<?php
declare(strict_types=1);
namespace App\Services;
use App\Repositories\AuditRepository;use App\Security\Auth;use PDO;use RuntimeException;
final readonly class IpBlockService
{
 public function __construct(private PDO $db,private AuditRepository $audit){}
 public function blocked(string $ip):bool{if(!filter_var($ip,FILTER_VALIDATE_IP))return false;$s=$this->db->prepare('SELECT 1 FROM blocked_ips WHERE ip=? AND active=1 AND (expires_at IS NULL OR expires_at>NOW())');$s->execute([$ip]);return (bool)$s->fetchColumn();}
 public function add(string $ip,string $reason,int $hours,string $currentIp):void
 {
  $ip=trim($ip);$reason=trim($reason);if(!filter_var($ip,FILTER_VALIDATE_IP))throw new RuntimeException('Dirección IP inválida.');if(hash_equals($currentIp,$ip))throw new RuntimeException('No puedes bloquear la IP de tu sesión administrativa actual.');if($this->privateOrLocal($ip))throw new RuntimeException('No se permite bloquear loopback ni rangos privados desde esta pantalla.');if($reason===''||strlen($reason)>255)throw new RuntimeException('Escribe un motivo válido.');$expires=$hours>0?date('Y-m-d H:i:s',time()+min($hours,8760)*3600):null;
  $this->db->prepare("INSERT INTO blocked_ips(ip,reason,source,event_count,active,expires_at,created_by) VALUES(?,?,'manual',0,1,?,?) ON DUPLICATE KEY UPDATE id=LAST_INSERT_ID(id),reason=VALUES(reason),source='manual',active=1,expires_at=VALUES(expires_at),created_by=VALUES(created_by)")->execute([$ip,$reason,$expires,Auth::id()]);$this->audit->record('security.ip_blocked','blocked_ip',(int)$this->db->lastInsertId(),['ip'=>$ip,'reason'=>$reason,'expires_at'=>$expires]);
 }
 public function remove(int $id):void{$s=$this->db->prepare('SELECT ip FROM blocked_ips WHERE id=?');$s->execute([$id]);$ip=$s->fetchColumn()?:throw new RuntimeException('Bloqueo no encontrado.');$this->db->prepare('UPDATE blocked_ips SET active=0 WHERE id=?')->execute([$id]);$this->audit->record('security.ip_unblocked','blocked_ip',$id,['ip'=>$ip]);}
 private function privateOrLocal(string $ip):bool{return filter_var($ip,FILTER_VALIDATE_IP,FILTER_FLAG_NO_PRIV_RANGE|FILTER_FLAG_NO_RES_RANGE)===false;}
}
