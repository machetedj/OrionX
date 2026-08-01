<?php
declare(strict_types=1);
namespace App\Services;
use PDO;use RuntimeException;use Throwable;
final readonly class ServerHealthService
{
 public function __construct(private PDO $db){}
 public function heartbeat(int $serverId,array $input):array
 {
  $metrics=[];foreach(['cpu','ram','disk'] as $key){$v=filter_var($input[$key]??null,FILTER_VALIDATE_FLOAT);if($v===false||$v<0||$v>100)throw new RuntimeException("Métrica {$key} inválida.");$metrics[$key]=(float)$v;}$services=[];foreach(['ffmpeg_status','nginx_status','redis_status','agent_status'] as $key){$value=(string)($input[$key]??'unknown');if(!in_array($value,['unknown','ok','error'],true))throw new RuntimeException("Estado {$key} inválido.");$services[$key]=$value;}
  $this->db->beginTransaction();try{$s=$this->db->prepare('SELECT status FROM servers WHERE id=? FOR UPDATE');$s->execute([$serverId]);$before=$s->fetchColumn();if($before===false)throw new RuntimeException('Servidor no encontrado.');$status=$this->status((string)$before,$metrics,$services);$sessions=max(0,(int)($input['active_sessions']??0));$streams=max(0,(int)($input['active_streams']??0));$users=max(0,(int)($input['connected_users']??0));
   $this->db->prepare('INSERT INTO server_metrics(server_id,cpu,ram,disk,network_rx_bps,network_tx_bps,active_sessions,active_streams,connected_users) VALUES(?,?,?,?,?,?,?,?,?)')->execute([$serverId,$metrics['cpu'],$metrics['ram'],$metrics['disk'],max(0,(int)($input['network_rx_bps']??0)),max(0,(int)($input['network_tx_bps']??0)),$sessions,$streams,$users]);
   $this->db->prepare('UPDATE servers SET status=?,last_heartbeat_at=NOW(),installed_version=?,ffmpeg_status=?,nginx_status=?,redis_status=?,agent_status=?,quarantined_reason=? WHERE id=?')->execute([$status,substr((string)($input['installed_version']??''),0,50)?:null,$services['ffmpeg_status'],$services['nginx_status'],$services['redis_status'],$services['agent_status'],$status==='quarantined'?'Fallo crítico reportado por heartbeat':null,$serverId]);
   if($status!==$before){$this->event($serverId,(string)$before,$status,'Evaluación automática del heartbeat',$metrics+$services);if(in_array($status,['quarantined','offline'],true))$this->alert($serverId,'critical','Balanceador aislado','El servidor cambió de '.$before.' a '.$status.'.');}$this->db->commit();return ['status'=>$status,'heartbeat_at'=>date(DATE_ATOM)];
  }catch(Throwable $e){if($this->db->inTransaction())$this->db->rollBack();throw $e;}
 }
 public function isolateStale(int $seconds=90):int
 {
  $seconds=max(30,min(600,$seconds));$servers=$this->db->query("SELECT id,status FROM servers WHERE status IN ('online','degraded') AND (last_heartbeat_at IS NULL OR last_heartbeat_at<DATE_SUB(NOW(),INTERVAL {$seconds} SECOND))")->fetchAll();foreach($servers as $server){$this->db->prepare("UPDATE servers SET status='offline',quarantined_reason='Heartbeat vencido' WHERE id=?")->execute([$server['id']]);$this->event((int)$server['id'],$server['status'],'offline','Heartbeat vencido',[]);$this->alert((int)$server['id'],'critical','Heartbeat vencido','El balanceador fue retirado de nuevas asignaciones.');}return count($servers);
 }
 public function setMode(int $id,string $mode):void{if(!in_array($mode,['online','maintenance','draining'],true))throw new RuntimeException('Modo inválido.');$s=$this->db->prepare('SELECT status FROM servers WHERE id=?');$s->execute([$id]);$before=$s->fetchColumn();if($before===false)throw new RuntimeException('Servidor no encontrado.');$this->db->prepare('UPDATE servers SET status=?,drain_started_at=IF(?=\'draining\',NOW(),NULL) WHERE id=?')->execute([$mode,$mode,$id]);$this->event($id,(string)$before,$mode,'Cambio administrativo',[]);}
 private function status(string $current,array $m,array $s):string{if(in_array($current,['maintenance','draining'],true))return $current;if($s['agent_status']==='error'||$s['nginx_status']==='error'||$m['disk']>=98||$m['cpu']>=98||$m['ram']>=98)return 'quarantined';if(in_array('error',$s,true)||$m['disk']>=90||$m['cpu']>=90||$m['ram']>=90)return 'degraded';return 'online';}
 private function event(int $id,?string $before,string $after,string $reason,array $metrics):void{$this->db->prepare('INSERT INTO server_availability_events(server_id,previous_status,new_status,reason,metrics) VALUES(?,?,?,?,?)')->execute([$id,$before,$after,$reason,json_encode($metrics,JSON_THROW_ON_ERROR)]);}
 private function alert(int $id,string $severity,string $title,string $message):void{$open=$this->db->prepare("SELECT 1 FROM alerts WHERE entity_type='server' AND entity_id=? AND title=? AND status='open'");$open->execute([$id,$title]);if(!$open->fetchColumn())$this->db->prepare("INSERT INTO alerts(type,severity,entity_type,entity_id,title,message) VALUES('server_health',?,'server',?,?,?)")->execute([$severity,$id,$title,$message]);}
}
