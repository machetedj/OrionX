<?php
declare(strict_types=1);
namespace App\Services;
use PDO;use RuntimeException;
final readonly class NodeMediaTaskService
{
 public function __construct(private PDO $db,private RemoteMediaService $media){}
 public function claim(int $serverId):?array{$this->db->beginTransaction();try{$s=$this->db->prepare("SELECT * FROM node_media_tasks WHERE server_id=? AND status='pending' ORDER BY created_at LIMIT 1 FOR UPDATE");$s->execute([$serverId]);$task=$s->fetch();if(!$task){$this->db->commit();return null;}$this->db->prepare("UPDATE node_media_tasks SET status='claimed',attempts=attempts+1,claimed_at=NOW() WHERE id=?")->execute([$task['id']]);$this->db->prepare("UPDATE media_remote_runs SET status='running',started_at=COALESCE(started_at,NOW()),error_message=NULL WHERE id=?")->execute([$task['run_id']]);$this->db->commit();return ['id'=>$task['id'],'payload'=>json_decode($task['payload'],true,64,JSON_THROW_ON_ERROR)];}catch(\Throwable $e){if($this->db->inTransaction())$this->db->rollBack();throw $e;}}
 public function result(int $serverId,string $id,bool $success,array $result,?string $error):void{if(!preg_match('/^[a-f0-9]{32}$/',$id))throw new RuntimeException('Tarea inválida.');$s=$this->db->prepare("SELECT run_id FROM node_media_tasks WHERE id=? AND server_id=? AND status='claimed'");$s->execute([$id,$serverId]);$run=$s->fetchColumn();if($run===false)throw new RuntimeException('Tarea no disponible.');if($success){$summary=$this->media->acceptNodeResult((int)$run,$result);$this->db->prepare("UPDATE node_media_tasks SET status='completed',completed_at=NOW() WHERE id=?")->execute([$id]);}else{$this->db->prepare("UPDATE node_media_tasks SET status='failed',error_message=?,completed_at=NOW() WHERE id=?")->execute([substr((string)$error,0,65535),$id]);$this->media->failNodeRun((int)$run,(string)$error);}}
}
