<?php
declare(strict_types=1);
namespace App\Services;
use PDO;use RuntimeException;use Throwable;
final readonly class TaskWorker
{
 public function __construct(private TaskQueueService $queue,private LibraryScanner $scanner,private TmdbService $tmdb,private EpgService $epg,private StreamMonitorService $streams,private BackupService $backups,private ServerHealthService $health,private CertificateIssuer $certificates,private SshProvisioner $provisioner,private XuiImportService $xui,private RemoteMediaService $remoteMedia,private PDO $db){}
 public function runOne(string $worker):bool{$item=$this->queue->pop();if(!$item)return false;$job=$this->queue->reserve($item['id'],$worker);if(!$job)return false;try{$result=$this->handle($job);$this->queue->complete($job['id'],$result);return true;}catch(Throwable $e){$this->queue->fail($job,$e);return true;}}
 private function handle(array $job):array{$p=$job['payload'];return match($job['type']){
  'import_epg'=>$this->epg->import((int)($p['source_id']??0)),
  'import_movies'=>$this->scanner->scan((int)($p['library_id']??0),'movie'),
  'import_series'=>$this->scanner->scan((int)($p['library_id']??0),'series'),
  'query_tmdb'=>['tmdb'=>$this->tmdb->details((string)($p['type']??'movie'),(int)($p['tmdb_id']??0),(string)($p['language']??'es-ES'))],
  'test_stream'=>$this->streams->check((int)($p['source_id']??0)),
  'create_backup'=>$this->backups->run((int)($p['run_id']??0)),
  'cleanup_sessions'=>$this->cleanupSessions(),'rotate_logs'=>$this->rotateLogs(),
  'sync_balancers'=>['isolated'=>$this->health->isolateStale((int)($p['heartbeat_ttl']??90))],
  'issue_certificate'=>$this->certificates->issueMain((int)($p['request_id']??0)),
  'provision_balancer','sync_balancer','update_balancer'=>$this->provisioner->deploy((int)($p['deployment_id']??0)),
  'xui_import'=>$this->xui->run((int)($p['connection_id']??0),(bool)($p['replace']??false)),
  'xui_sql_upload'=>$this->xui->runUpload((int)($p['upload_id']??0)),
  'inventory_media_links','scan_remote_media','validate_remote_media','apply_media_links'=>$this->remoteMedia->run((int)($p['run_id']??0)),
  default=>throw new RuntimeException('Handler aún no configurado para '.$job['type'])};}
 private function cleanupSessions():array{$count=$this->db->exec("UPDATE active_sessions SET disconnected_at=NOW(),disconnect_reason='lease_expired' WHERE disconnected_at IS NULL AND last_seen_at<DATE_SUB(NOW(),INTERVAL 2 MINUTE)");return ['sessions_closed'=>$count];}
 private function rotateLogs():array{$categories=$this->db->query('SELECT category,retention_days FROM log_retention_policies')->fetchAll();$deleted=0;$s=$this->db->prepare('DELETE FROM system_logs WHERE category=? AND created_at<DATE_SUB(NOW(),INTERVAL ? DAY)');foreach($categories as $policy){$s->execute([$policy['category'],$policy['retention_days']]);$deleted+=$s->rowCount();}return ['logs_deleted'=>$deleted];}
}
