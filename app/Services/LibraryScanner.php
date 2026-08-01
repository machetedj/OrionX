<?php
declare(strict_types=1);
namespace App\Services;

use App\Domain\Media\EpisodeFilenameParser;
use PDO;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use Throwable;

final readonly class LibraryScanner
{
 private const EXTENSIONS=['mkv','mp4','avi','mov','m4v','webm','ts'];
 public function __construct(private PDO $db,private FfprobeAnalyzer $probe,private EpisodeFilenameParser $episodes){}
 public function scan(int $libraryId,string $type):array
 {
  if(!in_array($type,['movie','series'],true))throw new RuntimeException('Tipo de escaneo inválido.');
  $s=$this->db->prepare('SELECT * FROM storage_libraries WHERE id=? AND active=1');$s->execute([$libraryId]);$library=$s->fetch()?:throw new RuntimeException('Librería no encontrada.');
  if(!is_dir($library['mount_path']))throw new RuntimeException('El punto de montaje no está disponible.');
  $this->db->prepare("INSERT INTO media_scan_jobs(library_id,type,status,started_at) VALUES(?,?,'running',NOW())")->execute([$libraryId,$type]);$jobId=(int)$this->db->lastInsertId();$seen=0;$added=0;$conflicts=0;
  try{
   $iterator=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($library['mount_path'],RecursiveDirectoryIterator::SKIP_DOTS));
   foreach($iterator as $file){if(!$file->isFile()||!in_array(strtolower($file->getExtension()),self::EXTENSIONS,true))continue;$seen++;
    try{$analysis=$this->probe->analyze($library['mount_path'],$file->getPathname());if($this->known($libraryId,$analysis['relative_path'],$analysis['checksum_sha256']))continue;
     if($type==='series'){$parsed=$this->episodes->parse($analysis['relative_path']);if(!$parsed||!$parsed['series_title']){$this->conflict($jobId,$analysis['relative_path'],'ambiguous_episode',$parsed);$conflicts++;continue;}$this->importEpisode($libraryId,$analysis,$parsed);}
     else{$title=$this->movieTitle($file->getBasename('.'.$file->getExtension()));if($title===''){$this->conflict($jobId,$analysis['relative_path'],'ambiguous_movie',null);$conflicts++;continue;}$this->importMovie($libraryId,$analysis,$title);}
     $added++;
    }catch(Throwable $e){$this->conflict($jobId,str_replace('\\','/',$file->getPathname()),'probe_or_import_failed',['error'=>substr($e->getMessage(),0,500)]);$conflicts++;}
   }
   $this->db->prepare("UPDATE media_scan_jobs SET status='completed',files_seen=?,files_added=?,conflicts=?,completed_at=NOW() WHERE id=?")->execute([$seen,$added,$conflicts,$jobId]);return ['job_id'=>$jobId,'files_seen'=>$seen,'files_added'=>$added,'conflicts'=>$conflicts];
  }catch(Throwable $e){$this->db->prepare("UPDATE media_scan_jobs SET status='failed',files_seen=?,files_added=?,conflicts=?,error_message=?,completed_at=NOW() WHERE id=?")->execute([$seen,$added,$conflicts,substr($e->getMessage(),0,65535),$jobId]);throw $e;}
 }
 private function known(int $libraryId,string $path,string $checksum):bool{$s=$this->db->prepare('SELECT 1 FROM content_files WHERE (library_id=? AND relative_path=?) OR checksum_sha256=? LIMIT 1');$s->execute([$libraryId,$path,$checksum]);return (bool)$s->fetchColumn();}
 private function importMovie(int $libraryId,array $a,string $title):void{$this->db->beginTransaction();try{$slug=$this->slug($title).'-'.substr($a['checksum_sha256'],0,10);$this->db->prepare("INSERT INTO content_items(type,title,slug,status,metadata) VALUES('movie',?,?,'draft',?)")->execute([$title,$slug,json_encode(['needs_tmdb_review'=>true],JSON_THROW_ON_ERROR)]);$contentId=(int)$this->db->lastInsertId();$duration=(int)round((float)($a['probe']['format']['duration']??0));$this->db->prepare('INSERT INTO movies(content_item_id,original_title,duration_seconds) VALUES(?,?,?)')->execute([$contentId,$title,$duration?:null]);$fileId=$this->insertFile($contentId,$libraryId,$a);$this->insertTracks($fileId,$a['tracks']);$this->db->commit();}catch(Throwable $e){if($this->db->inTransaction())$this->db->rollBack();throw $e;}}
 private function importEpisode(int $libraryId,array $a,array $parsed):void{$this->db->beginTransaction();try{$seriesSlug=$this->slug((string)$parsed['series_title']);$s=$this->db->prepare("SELECT id FROM content_items WHERE type='series' AND slug=?");$s->execute([$seriesSlug]);$seriesId=(int)($s->fetchColumn()?:0);if(!$seriesId){$this->db->prepare("INSERT INTO content_items(type,title,slug,status,metadata) VALUES('series',?,?,'draft',?)")->execute([$parsed['series_title'],$seriesSlug,json_encode(['needs_tmdb_review'=>true],JSON_THROW_ON_ERROR)]);$seriesId=(int)$this->db->lastInsertId();}
   $s=$this->db->prepare('SELECT id FROM series_seasons WHERE series_id=? AND season_number=?');$s->execute([$seriesId,$parsed['season']]);$seasonId=(int)($s->fetchColumn()?:0);if(!$seasonId){$this->db->prepare('INSERT INTO series_seasons(series_id,season_number,title) VALUES(?,?,?)')->execute([$seriesId,$parsed['season'],$parsed['special']?'Especiales':'Temporada '.$parsed['season']]);$seasonId=(int)$this->db->lastInsertId();}
   $episodeTitle=$parsed['series_title'].' S'.str_pad((string)$parsed['season'],2,'0',STR_PAD_LEFT).'E'.str_pad((string)$parsed['episode'],2,'0',STR_PAD_LEFT);$episodeSlug=$seriesSlug.'-s'.$parsed['season'].'e'.$parsed['episode'];$this->db->prepare("INSERT INTO content_items(type,title,slug,status,metadata) VALUES('episode',?,?,'draft',?)")->execute([$episodeTitle,$episodeSlug,json_encode(['needs_tmdb_review'=>true],JSON_THROW_ON_ERROR)]);$episodeId=(int)$this->db->lastInsertId();$this->db->prepare('INSERT INTO series_episodes(season_id,content_item_id,episode_number) VALUES(?,?,?)')->execute([$seasonId,$episodeId,$parsed['episode']]);$fileId=$this->insertFile($episodeId,$libraryId,$a);$this->insertTracks($fileId,$a['tracks']);$this->db->commit();}catch(Throwable $e){if($this->db->inTransaction())$this->db->rollBack();throw $e;}}
 private function insertFile(int $contentId,int $libraryId,array $a):int{$this->db->prepare("INSERT INTO content_files(public_id,content_item_id,library_id,relative_path,size_bytes,checksum_sha256,probe_data,status,last_checked_at) VALUES(?,?,?,?,?,?,?,'available',NOW())")->execute([bin2hex(random_bytes(16)),$contentId,$libraryId,$a['relative_path'],$a['size_bytes'],$a['checksum_sha256'],json_encode($a['probe'],JSON_THROW_ON_ERROR)]);return (int)$this->db->lastInsertId();}
 private function insertTracks(int $fileId,array $tracks):void{$s=$this->db->prepare('INSERT INTO media_tracks(content_file_id,type,stream_index,codec,language,title,is_default,metadata) VALUES(?,?,?,?,?,?,?,?)');foreach($tracks as $t){$type=in_array($t['type'],['video','audio','subtitle'],true)?$t['type']:'video';$s->execute([$fileId,$type,$t['stream_index'],$t['codec'],$t['language'],$t['title'],$t['is_default']?1:0,json_encode(['width'=>$t['width'],'height'=>$t['height'],'fps'=>$t['fps']],JSON_THROW_ON_ERROR)]);}}
 private function conflict(int $jobId,string $path,string $reason,?array $parsed):void{$this->db->prepare('INSERT INTO media_import_conflicts(scan_job_id,relative_path,reason,parsed_data) VALUES(?,?,?,?)')->execute([$jobId,$path,$reason,$parsed?json_encode($parsed,JSON_THROW_ON_ERROR):null]);}
 private function movieTitle(string $name):string{$name=(string)preg_replace('/[._]+/',' ',$name);$name=(string)preg_replace('/\b(19|20)\d{2}\b.*$/','',$name);return trim($name," -\t");}
 private function slug(string $title):string{$slug=strtolower(trim((string)preg_replace('/[^A-Za-z0-9]+/','-',$title),'-'));return $slug?:'media';}
}
