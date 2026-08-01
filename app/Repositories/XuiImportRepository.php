<?php
declare(strict_types=1);
namespace App\Repositories;
use PDO;
final readonly class XuiImportRepository
{
 public function __construct(private PDO $db){}
 public function connections():array{return $this->db->query("SELECT id,name,host,port,database_name,username,table_prefix,status,last_tested_at,last_error,created_at FROM xui_source_connections ORDER BY id DESC")->fetchAll();}
 public function imports():array{return $this->db->query("SELECT i.*,c.name connection_name FROM xui_imports i LEFT JOIN xui_source_connections c ON c.id=i.connection_id ORDER BY i.id DESC LIMIT 100")->fetchAll();}
 public function tables(int $importId):array{$s=$this->db->prepare('SELECT * FROM xui_import_tables WHERE import_id=? ORDER BY source_table');$s->execute([$importId]);return $s->fetchAll();}
}
