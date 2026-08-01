<?php
declare(strict_types=1);
namespace App\Repositories;
use PDO;
final readonly class MovieRepository
{
 public function __construct(private PDO $db){}
 public function movies():array{return $this->db->query("SELECT c.id,c.title,c.status,c.tmdb_id,m.release_year,m.poster_url,l.name library_name,s.name server_name,f.status file_status FROM content_items c JOIN movies m ON m.content_item_id=c.id LEFT JOIN content_files f ON f.content_item_id=c.id LEFT JOIN storage_libraries l ON l.id=f.library_id LEFT JOIN servers s ON s.id=COALESCE(f.server_id,l.server_id) WHERE c.type='movie' ORDER BY c.id DESC LIMIT 300")->fetchAll();}
 public function categories():array{return $this->db->query("SELECT id,name FROM categories WHERE type='movie' AND active=1 ORDER BY name")->fetchAll();}
 public function libraries():array{return $this->db->query("SELECT l.id,l.name,l.mount_path,l.server_id,s.name server_name,s.status server_status FROM storage_libraries l LEFT JOIN servers s ON s.id=l.server_id WHERE l.active=1 AND l.content_type IN ('movie','mixed') ORDER BY COALESCE(s.name,'Principal'),l.name")->fetchAll();}
 public function packages():array{return $this->db->query('SELECT id,name FROM packages WHERE active=1 ORDER BY name')->fetchAll();}
}
