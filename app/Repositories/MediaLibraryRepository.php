<?php
declare(strict_types=1);
namespace App\Repositories;
use PDO;
final readonly class MediaLibraryRepository
{
 public function __construct(private PDO $db){}
 public function libraries():array{return $this->db->query("SELECT l.*,s.name server_name,(SELECT COUNT(*) FROM content_files f WHERE f.library_id=l.id) files_count FROM storage_libraries l LEFT JOIN servers s ON s.id=l.server_id ORDER BY l.id DESC")->fetchAll();}
 public function servers():array{return $this->db->query("SELECT id,name,status FROM servers ORDER BY name")->fetchAll();}
 public function jobs():array{return $this->db->query("SELECT j.*,l.name library_name FROM media_scan_jobs j JOIN storage_libraries l ON l.id=j.library_id ORDER BY j.id DESC LIMIT 100")->fetchAll();}
 public function conflicts():array{return $this->db->query("SELECT c.*,l.name library_name FROM media_import_conflicts c JOIN media_scan_jobs j ON j.id=c.scan_job_id JOIN storage_libraries l ON l.id=j.library_id WHERE c.status='pending' ORDER BY c.id DESC LIMIT 200")->fetchAll();}
 public function remoteRuns():array{return $this->db->query('SELECT r.*,s.name server_name,l.name library_name FROM media_remote_runs r JOIN servers s ON s.id=r.server_id JOIN storage_libraries l ON l.id=r.library_id ORDER BY r.id DESC LIMIT 100')->fetchAll();}
 public function links():array{return $this->db->query('SELECT i.*,l.name library_name FROM media_symlink_inventory i JOIN storage_libraries l ON l.id=i.library_id ORDER BY i.id DESC LIMIT 500')->fetchAll();}
}
