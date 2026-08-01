<?php
declare(strict_types=1);
namespace App\Repositories;
use PDO;
final readonly class LiveChannelRepository
{
 public function __construct(private PDO $db){}
 public function all():array{return $this->db->query("SELECT c.id,c.title,c.status,l.channel_number,l.country,l.language,l.delivery_mode,l.monitoring_enabled,cat.name category,(SELECT COUNT(*) FROM stream_sources s WHERE s.channel_id=c.id) source_count FROM content_items c JOIN live_channels l ON l.content_item_id=c.id LEFT JOIN categories cat ON cat.id=c.category_id WHERE c.type='live' ORDER BY COALESCE(l.channel_number,999999),c.title")->fetchAll();}
 public function sources(int $channelId):array{$s=$this->db->prepare('SELECT id,name,priority,authorized,active,status,last_checked_at,consecutive_failures FROM stream_sources WHERE channel_id=? ORDER BY priority,id');$s->execute([$channelId]);return $s->fetchAll();}
 public function categories():array{return $this->db->query("SELECT id,name FROM categories WHERE type='live' AND active=1 ORDER BY name")->fetchAll();}
 public function servers():array{return $this->db->query("SELECT id,name FROM servers WHERE status IN ('pending','online') ORDER BY name")->fetchAll();}
}
