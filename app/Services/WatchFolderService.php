<?php
declare(strict_types=1);
namespace App\Services;
use App\Repositories\AuditRepository;use App\Security\Auth;use PDO;use RuntimeException;
final readonly class WatchFolderService
{
 public function __construct(private PDO $db,private TaskQueueService $queue,private RemoteMediaService $remote,private AuditRepository $audit){}
 public function create(array $input):int
 {
  $name=trim((string)($input['name']??''));$path=rtrim(str_replace('\\','/',trim((string)($input['path']??''))),'/');$type=(string)($input['content_type']??'');$server=(int)($input['server_id']??0)?:null;$interval=max(1,min(1440,(int)($input['interval_minutes']??10)));
  if($name===''||strlen($name)>150)throw new RuntimeException('Escribe un nombre para la carpeta vigilada.');if(!in_array($type,['movie','series'],true))throw new RuntimeException('Selecciona Películas o Series.');if(!str_starts_with($path,'/')||$path==='/'||str_contains($path,"\0")||preg_match('#(?:^|/)\.\.(?:/|$)#',$path))throw new RuntimeException('La carpeta debe ser una ruta absoluta específica.');
  $known=$this->db->prepare('SELECT id FROM storage_libraries WHERE mount_path=? AND server_id <=> ?');$known->execute([$path,$server]);$library=(int)($known->fetchColumn()?:0);$this->db->beginTransaction();try{if(!$library){$this->db->prepare("INSERT INTO storage_libraries(name,type,mount_path,content_type,priority,min_free_bytes,active,server_id) VALUES(?,'mounted',?,?,100,10737418240,1,?)")->execute([$name,$path,$type,$server]);$library=(int)$this->db->lastInsertId();}else{$this->db->prepare('UPDATE storage_libraries SET content_type=?,active=1 WHERE id=?')->execute([$type,$library]);}$this->db->prepare('INSERT INTO watch_folders(library_id,content_type,interval_minutes,created_by) VALUES(?,?,?,?)')->execute([$library,$type,$interval,Auth::id()]);$id=(int)$this->db->lastInsertId();$this->db->commit();$this->audit->record('storage.watch_folder_created','watch_folder',$id,['path'=>$path,'type'=>$type,'server_id'=>$server,'interval'=>$interval]);return $id;}catch(\Throwable $e){if($this->db->inTransaction())$this->db->rollBack();throw $e;}
 }
 public function run(int $id):void{$s=$this->db->prepare('SELECT w.*,l.server_id FROM watch_folders w JOIN storage_libraries l ON l.id=w.library_id WHERE w.id=? AND w.active=1');$s->execute([$id]);$watch=$s->fetch()?:throw new RuntimeException('Watch Folder no disponible.');$this->dispatch($watch);}
 public function disable(int $id):void{$this->db->prepare('UPDATE watch_folders SET active=0 WHERE id=?')->execute([$id]);$this->audit->record('storage.watch_folder_disabled','watch_folder',$id,[]);}
 public function dispatchDue():int{$rows=$this->db->query("SELECT w.*,l.server_id FROM watch_folders w JOIN storage_libraries l ON l.id=w.library_id WHERE w.active=1 AND (w.last_dispatched_at IS NULL OR w.last_dispatched_at<=DATE_SUB(NOW(),INTERVAL w.interval_minutes MINUTE)) ORDER BY w.last_dispatched_at LIMIT 100")->fetchAll();foreach($rows as $row)$this->dispatch($row);return count($rows);}
 private function dispatch(array $watch):void
 {
  $this->db->prepare('UPDATE watch_folders SET last_dispatched_at=NOW() WHERE id=?')->execute([$watch['id']]);if($watch['server_id']!==null){$run=$this->remote->request((int)$watch['library_id'],'scan');$this->db->prepare('UPDATE watch_folders SET last_run_id=?,last_job_id=NULL WHERE id=?')->execute([$run,$watch['id']]);}else{$job=$this->queue->enqueue($watch['content_type']==='series'?'import_series':'import_movies',['library_id'=>(int)$watch['library_id']],20,'watch-'.$watch['id'].'-'.date('YmdHi'));$this->db->prepare('UPDATE watch_folders SET last_job_id=?,last_run_id=NULL WHERE id=?')->execute([$job,$watch['id']]);}
 }
}
