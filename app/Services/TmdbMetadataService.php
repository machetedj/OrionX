<?php
declare(strict_types=1);
namespace App\Services;
use App\Repositories\AuditRepository;use PDO;use RuntimeException;use Throwable;
final readonly class TmdbMetadataService
{
 public function __construct(private PDO $db,private TmdbService $tmdb,private AuditRepository $audit){}
 public function select(int $contentId,int $tmdbId,string $language='es-ES'):void
 {
  $s=$this->db->prepare('SELECT type FROM content_items WHERE id=?');$s->execute([$contentId]);$internal=$s->fetchColumn();$type=$internal==='movie'?'movie':($internal==='series'?'tv':null);if(!$type)throw new RuntimeException('Tipo de contenido no compatible con TMDB.');$data=$this->tmdb->details($type,$tmdbId,$language);
  $this->db->beginTransaction();try{$this->db->prepare('UPDATE content_items SET tmdb_id=?,metadata=? WHERE id=?')->execute([$tmdbId,json_encode($data,JSON_THROW_ON_ERROR),$contentId]);if($type==='movie'){$credits=$data['credits']??[];$director='';foreach($credits['crew']??[] as $person)if(($person['job']??'')==='Director'){$director=$person['name']??'';break;}$this->db->prepare('UPDATE movies SET original_title=?,description=?,release_year=?,duration_seconds=?,genres=?,cast_members=?,director=?,language=?,country=?,poster_url=?,backdrop_url=? WHERE content_item_id=?')->execute([$data['original_title']??null,$data['overview']??null,isset($data['release_date'])?(int)substr($data['release_date'],0,4):null,isset($data['runtime'])?(int)$data['runtime']*60:null,json_encode($data['genres']??[],JSON_THROW_ON_ERROR),json_encode(array_slice($credits['cast']??[],0,30),JSON_THROW_ON_ERROR),$director,$data['original_language']??null,$data['origin_country'][0]??null,$data['poster_path']??null,$data['backdrop_path']??null,$contentId]);}$this->audit->record('tmdb.match_selected','content_item',$contentId,['tmdb_id'=>$tmdbId,'language'=>$language]);$this->db->commit();}catch(Throwable $e){if($this->db->inTransaction())$this->db->rollBack();throw $e;}
 }
}
