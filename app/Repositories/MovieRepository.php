<?php
declare(strict_types=1);
namespace App\Repositories;
use PDO;
final readonly class MovieRepository
{
 public function __construct(private PDO $db){}
 public function browse(array $input):array
 {
  $page=max(1,(int)($input['page']??1));$per=in_array((int)($input['per_page']??50),[25,50,100,200],true)?(int)$input['per_page']:50;$where=["c.type='movie'"];$params=[];$search=trim((string)($input['search']??''));
  if($search!==''){$where[]='(c.title LIKE ? OR m.original_title LIKE ? OR CAST(c.id AS CHAR)=?)';$like='%'.str_replace(['%','_'],['\\%','\\_'],$search).'%';array_push($params,$like,$like,$search);}
  foreach(['server_id'=>'COALESCE(f.server_id,l.server_id)','category_id'=>'c.category_id'] as $key=>$column)if((int)($input[$key]??0)>0){$where[]="$column=?";$params[]=(int)$input[$key];}
  $status=(string)($input['status']??'');if(in_array($status,['draft','active','disabled','missing'],true)){$where[]='c.status=?';$params[]=$status;}$tmdb=(string)($input['tmdb']??'');if($tmdb==='yes')$where[]='c.tmdb_id IS NOT NULL';elseif($tmdb==='no')$where[]='c.tmdb_id IS NULL';
  foreach(['audio','video'] as $codec)if(trim((string)($input[$codec]??''))!==''){$where[]="JSON_SEARCH(f.probe_data,'one',?,NULL,'$.streams[*].codec_name') IS NOT NULL";$params[]=trim((string)$input[$codec]);}$quality=(int)($input['quality']??0);if($quality>0){$where[]="CAST(JSON_UNQUOTE(JSON_EXTRACT(f.probe_data,'$.streams[0].height')) AS UNSIGNED)>=?";$params[]=$quality;}
  $from=' FROM content_items c JOIN movies m ON m.content_item_id=c.id LEFT JOIN categories cat ON cat.id=c.category_id LEFT JOIN content_files f ON f.id=(SELECT MIN(f2.id) FROM content_files f2 WHERE f2.content_item_id=c.id) LEFT JOIN storage_libraries l ON l.id=f.library_id LEFT JOIN servers s ON s.id=COALESCE(f.server_id,l.server_id)';$condition=' WHERE '.implode(' AND ',$where);$count=$this->db->prepare('SELECT COUNT(*)'.$from.$condition);$count->execute($params);$total=(int)$count->fetchColumn();$offset=($page-1)*$per;
  $sql="SELECT c.id,c.title,c.status,c.tmdb_id,m.release_year,m.poster_url,m.rating,m.duration_seconds,cat.name category_name,l.name library_name,s.name server_name,f.public_id,f.status file_status,f.size_bytes,JSON_UNQUOTE(JSON_EXTRACT(f.probe_data,'$.format.bit_rate')) bit_rate,JSON_UNQUOTE(JSON_EXTRACT(f.probe_data,'$.format.duration')) probe_duration,JSON_UNQUOTE(JSON_EXTRACT(f.probe_data,'$.streams[0].width')) width,JSON_UNQUOTE(JSON_EXTRACT(f.probe_data,'$.streams[0].height')) height,JSON_UNQUOTE(JSON_EXTRACT(f.probe_data,'$.streams[0].codec_name')) video_codec,(SELECT COUNT(*) FROM active_sessions a WHERE a.content_item_id=c.id AND a.disconnected_at IS NULL) clients".$from.$condition.' ORDER BY c.id DESC LIMIT '.$per.' OFFSET '.$offset;$statement=$this->db->prepare($sql);$statement->execute($params);$items=$statement->fetchAll();
  foreach($items as &$item){$probe=$item['public_id']?$this->probe((int)$item['id']):[];$item['audio_codec']=$probe['audio']??null;$item['video_codec']=$probe['video']??$item['video_codec'];}$pages=max(1,(int)ceil($total/$per));return compact('items','total','page','per','pages');
 }
 private function probe(int $content):array{$s=$this->db->prepare("SELECT type,codec FROM media_tracks mt JOIN content_files f ON f.id=mt.content_file_id WHERE f.content_item_id=? AND type IN ('video','audio') ORDER BY mt.is_default DESC,mt.stream_index");$s->execute([$content]);$out=[];foreach($s->fetchAll() as $row)$out[$row['type']]??=$row['codec'];return $out;}
 public function categories():array{return $this->db->query("SELECT id,name FROM categories WHERE type='movie' AND active=1 ORDER BY name")->fetchAll();}
 public function libraries():array{return $this->db->query("SELECT l.id,l.name,l.mount_path,l.server_id,s.name server_name,s.status server_status FROM storage_libraries l LEFT JOIN servers s ON s.id=l.server_id WHERE l.active=1 AND l.content_type IN ('movie','mixed') ORDER BY COALESCE(s.name,'Principal'),l.name")->fetchAll();}
 public function servers():array{return $this->db->query('SELECT id,name FROM servers ORDER BY name')->fetchAll();}
 public function packages():array{return $this->db->query('SELECT id,name FROM packages WHERE active=1 ORDER BY name')->fetchAll();}
 public function bulkItems(array $input):array
 {
  $type=in_array(($input['type']??''),['movie','episode'],true)?$input['type']:'';$status=in_array(($input['status']??''),['draft','active','disabled','missing'],true)?$input['status']:'';$where=["c.type IN ('movie','episode')"];$params=[];$search=trim((string)($input['search']??''));
  if($type!==''){$where[]='c.type=?';$params[]=$type;}if($status!==''){$where[]='c.status=?';$params[]=$status;}if($search!==''){$where[]='(c.title LIKE ? OR CAST(c.id AS CHAR)=?)';$params[]='%'.str_replace(['%','_'],['\\%','\\_'],$search).'%';$params[]=$search;}
  $sql="SELECT c.id,c.type,c.title,c.status,c.tmdb_id,c.category_id,cat.name category_name,s.name server_name,ss.season_number,se.episode_number,parent.title series_title FROM content_items c LEFT JOIN categories cat ON cat.id=c.category_id LEFT JOIN content_files f ON f.id=(SELECT MIN(f2.id) FROM content_files f2 WHERE f2.content_item_id=c.id) LEFT JOIN storage_libraries l ON l.id=f.library_id LEFT JOIN servers s ON s.id=COALESCE(f.server_id,l.server_id) LEFT JOIN series_episodes se ON se.content_item_id=c.id LEFT JOIN series_seasons ss ON ss.id=se.season_id LEFT JOIN content_items parent ON parent.id=ss.series_id WHERE ".implode(' AND ',$where).' ORDER BY c.id DESC LIMIT 500';$q=$this->db->prepare($sql);$q->execute($params);return $q->fetchAll();
 }
 public function bulkCategories():array{return $this->db->query("SELECT id,name,type FROM categories WHERE type IN ('movie','series') AND active=1 ORDER BY type,name")->fetchAll();}
}
