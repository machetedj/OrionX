<?php
declare(strict_types=1);
namespace App\Services;
use App\Repositories\AuditRepository;use PDO;use RuntimeException;use Throwable;
final readonly class ContentMoveService
{
 public function __construct(private PDO $db,private AuditRepository $audit){}
 public function move(array $in):array
 {
  $source=(int)($in['source_server_id']??0);$target=(int)($in['target_server_id']??0);$types=array_values(array_intersect(['live','movie','series'],(array)($in['types']??[])));if($source<1||$target<1||$source===$target)throw new RuntimeException('Selecciona dos servidores diferentes.');if(!$types)throw new RuntimeException('Selecciona streaming, películas o series.');if(trim((string)($in['confirmation']??''))!=='MOVER')throw new RuntimeException('Escribe MOVER para confirmar.');$q=$this->db->prepare('SELECT id,name,status FROM servers WHERE id IN (?,?)');$q->execute([$source,$target]);$servers=[];foreach($q->fetchAll() as $s)$servers[(int)$s['id']]=$s;if(!isset($servers[$source],$servers[$target]))throw new RuntimeException('Servidor no válido.');if(in_array($servers[$target]['status'],['maintenance','offline','quarantined'],true))throw new RuntimeException('El servidor destino no está disponible para recibir contenido.');$moved=['live'=>0,'movie'=>0,'series'=>0];
  $this->db->beginTransaction();try{if(in_array('live',$types,true)){$q=$this->db->prepare('UPDATE live_channels SET assigned_server_id=?,preferred_server_id=? WHERE assigned_server_id=?');$q->execute([$target,$target,$source]);$moved['live']=$q->rowCount();}if(in_array('movie',$types,true)){$q=$this->db->prepare("UPDATE content_files f JOIN content_items c ON c.id=f.content_item_id JOIN storage_libraries sl ON sl.id=f.library_id SET f.server_id=? WHERE COALESCE(f.server_id,sl.server_id)=? AND c.type='movie'");$q->execute([$target,$source]);$moved['movie']=$q->rowCount();}if(in_array('series',$types,true)){$q=$this->db->prepare("UPDATE content_files f JOIN content_items c ON c.id=f.content_item_id JOIN storage_libraries sl ON sl.id=f.library_id SET f.server_id=? WHERE COALESCE(f.server_id,sl.server_id)=? AND c.type='episode'");$q->execute([$target,$source]);$moved['series']=$q->rowCount();}$this->db->commit();$this->audit->record('server.content_bulk_moved','server',$target,['source_server_id'=>$source,'target_server_id'=>$target,'types'=>$types,'moved'=>$moved,'active_sessions_preserved'=>true]);return $moved;}catch(Throwable $e){if($this->db->inTransaction())$this->db->rollBack();throw $e;}
 }
}
