<?php
declare(strict_types=1);
namespace App\Repositories;
use PDO;
final readonly class XuiImportRepository
{
 public function __construct(private PDO $db){}
 public function connections():array{return $this->db->query("SELECT id,name,host,port,database_name,username,table_prefix,status,last_tested_at,last_error,created_at FROM xui_source_connections ORDER BY id DESC")->fetchAll();}
 public function imports():array{return $this->db->query("SELECT i.*,c.name connection_name FROM xui_imports i LEFT JOIN xui_source_connections c ON c.id=i.connection_id ORDER BY i.id DESC LIMIT 100")->fetchAll();}
 public function uploads():array{return $this->db->query("SELECT u.id,u.original_name,u.size_bytes,u.sha256,u.status,u.error_message,u.created_at,u.completed_at,i.id import_id,i.tables_total,i.tables_copied,i.rows_copied,t.source_table current_table,t.source_rows current_source_rows,t.copied_rows current_copied_rows FROM xui_sql_uploads u LEFT JOIN xui_source_connections c ON c.id=u.connection_id LEFT JOIN xui_imports i ON i.id=(SELECT MAX(ii.id) FROM xui_imports ii WHERE ii.source_database=c.database_name) LEFT JOIN xui_import_tables t ON t.import_id=i.id AND t.status='copying' ORDER BY u.id DESC LIMIT 100")->fetchAll();}
 public function conversions():array{return $this->db->query('SELECT r.*,i.source_database FROM xui_conversion_runs r JOIN xui_imports i ON i.id=r.import_id ORDER BY r.id DESC LIMIT 100')->fetchAll();}
 public function conflicts(int $runId):array{$s=$this->db->prepare('SELECT entity_type,legacy_id,reason,details,status,created_at FROM xui_conversion_conflicts WHERE run_id=? ORDER BY id LIMIT 1000');$s->execute([$runId]);return $s->fetchAll();}
 public function tables(int $importId):array{$s=$this->db->prepare('SELECT * FROM xui_import_tables WHERE import_id=? ORDER BY source_table');$s->execute([$importId]);return $s->fetchAll();}
}
