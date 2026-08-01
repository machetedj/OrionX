<?php
declare(strict_types=1);
namespace App\Repositories;
use PDO;
final readonly class ContentMoveRepository
{
 public function __construct(private PDO $db){}
 public function servers():array{return $this->db->query("SELECT id,name,status,region,last_heartbeat_at FROM servers ORDER BY name")->fetchAll();}
 public function counts():array{return $this->db->query("SELECT s.id,s.name,s.status,s.region,(SELECT COUNT(*) FROM live_channels l WHERE l.assigned_server_id=s.id) live_count,(SELECT COUNT(*) FROM content_files f JOIN content_items c ON c.id=f.content_item_id JOIN storage_libraries sl ON sl.id=f.library_id WHERE COALESCE(f.server_id,sl.server_id)=s.id AND c.type='movie') movie_count,(SELECT COUNT(*) FROM content_files f JOIN content_items c ON c.id=f.content_item_id JOIN storage_libraries sl ON sl.id=f.library_id WHERE COALESCE(f.server_id,sl.server_id)=s.id AND c.type='episode') series_count,(SELECT COUNT(*) FROM active_sessions a WHERE a.server_id=s.id AND a.disconnected_at IS NULL) active_sessions FROM servers s ORDER BY s.name")->fetchAll();}
}
