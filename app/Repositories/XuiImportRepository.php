<?php
declare(strict_types=1);
namespace App\Repositories;
use PDO;
final readonly class XuiImportRepository
{
 public function __construct(private PDO $db){}
 public function connections():array{return $this->db->query("SELECT id,name,host,port,database_name,username,table_prefix,status,last_tested_at,last_error,created_at FROM xui_source_connections ORDER BY id DESC")->fetchAll();}
 public function imports():array{return $this->db->query("SELECT i.*,c.name connection_name FROM xui_imports i LEFT JOIN xui_source_connections c ON c.id=i.connection_id ORDER BY i.id DESC LIMIT 100")->fetchAll();}
 public function uploads():array{return $this->db->query('SELECT id,original_name,size_bytes,sha256,status,error_message,created_at,completed_at FROM xui_sql_uploads ORDER BY id DESC LIMIT 100')->fetchAll();}
 public function conversions():array{return $this->db->query('SELECT r.*,i.source_database FROM xui_conversion_runs r JOIN xui_imports i ON i.id=r.import_id ORDER BY r.id DESC LIMIT 100')->fetchAll();}
 public function conflicts(int $runId):array{$s=$this->db->prepare('SELECT entity_type,legacy_id,reason,details,status,created_at FROM xui_conversion_conflicts WHERE run_id=? ORDER BY id LIMIT 1000');$s->execute([$runId]);return $s->fetchAll();}
 public function tables(int $importId):array{$s=$this->db->prepare('SELECT * FROM xui_import_tables WHERE import_id=? ORDER BY source_table');$s->execute([$importId]);return $s->fetchAll();}
}
