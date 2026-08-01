<?php
declare(strict_types=1);
namespace App\Repositories;
use PDO;
final readonly class DashboardRepository
{
 public function __construct(private PDO $db){}
 public function data():array
 {
  $queries=['active_users'=>"SELECT COUNT(*) FROM end_user_accounts WHERE deleted_at IS NULL AND status='active' AND (expires_at IS NULL OR expires_at>NOW())",'expired_users'=>"SELECT COUNT(*) FROM end_user_accounts WHERE deleted_at IS NULL AND (status='expired' OR expires_at<=NOW())",'connections'=>"SELECT COUNT(*) FROM active_sessions WHERE disconnected_at IS NULL",'active_streams'=>"SELECT COUNT(*) FROM content_items WHERE type='live' AND status='active'",'stream_errors'=>"SELECT COUNT(*) FROM stream_sources WHERE status='failed'",'movies'=>"SELECT COUNT(*) FROM content_items WHERE type='movie'",'series'=>"SELECT COUNT(*) FROM content_items WHERE type='series'",'episodes'=>"SELECT COUNT(*) FROM content_items WHERE type='episode'",'resellers'=>"SELECT COUNT(*) FROM resellers WHERE active=1",'credits_consumed'=>"SELECT COALESCE(ABS(SUM(amount)),0) FROM reseller_credit_transactions WHERE amount<0",'servers_active'=>"SELECT COUNT(*) FROM servers WHERE status='online' AND last_heartbeat_at>DATE_SUB(NOW(),INTERVAL 90 SECOND)",'servers_problem'=>"SELECT COUNT(*) FROM servers WHERE status IN ('degraded','offline','quarantined') OR last_heartbeat_at<=DATE_SUB(NOW(),INTERVAL 90 SECOND)",'pending_jobs'=>"SELECT COUNT(*) FROM jobs WHERE status IN ('pending','reserved','running')",'storage_bytes'=>"SELECT COALESCE(SUM(size_bytes),0) FROM content_files WHERE status='available'",'security_alerts'=>"SELECT COUNT(*) FROM alerts WHERE status='open' AND severity='critical'"];$stats=[];foreach($queries as $key=>$sql)$stats[$key]=(int)$this->db->query($sql)->fetchColumn();
  $metrics=$this->db->query("SELECT COALESCE(AVG(m.cpu),0) cpu,COALESCE(AVG(m.ram),0) ram,COALESCE(SUM(m.network_rx_bps+m.network_tx_bps),0) traffic FROM server_metrics m JOIN (SELECT server_id,MAX(id) id FROM server_metrics GROUP BY server_id) latest ON latest.id=m.id")->fetch()?:[];
  return ['stats'=>$stats,'metrics'=>$metrics,'alerts'=>$this->db->query("SELECT severity,title,message,created_at FROM alerts WHERE status='open' ORDER BY FIELD(severity,'critical','warning','info'),id DESC LIMIT 10")->fetchAll(),'events'=>$this->db->query('SELECT action,entity_type,entity_id,created_at FROM audit_logs ORDER BY id DESC LIMIT 10')->fetchAll(),'jobs'=>$this->db->query("SELECT id,type,status,progress,worker,created_at FROM jobs WHERE status IN ('pending','running','failed') ORDER BY priority,created_at DESC LIMIT 10")->fetchAll()];
 }
}
