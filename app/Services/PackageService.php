<?php
declare(strict_types=1);
namespace App\Services;
use App\Repositories\AuditRepository;use PDO;use RuntimeException;use Throwable;
final readonly class PackageService
{
 public function __construct(private PDO $db,private AuditRepository $audit){}
 public function save(array $input):int
 {
  $id=(int)($input['id']??0);$name=trim((string)($input['name']??''));$description=trim((string)($input['description']??''));$days=(int)($input['duration_days']??0);$cost=(int)($input['credit_cost']??0);$connections=max(1,min(100,(int)($input['max_connections']??1)));if($name===''||strlen($name)>150)throw new RuntimeException('Nombre de paquete inválido.');if($days<0||$days>3650||$cost<0)throw new RuntimeException('Duración o coste inválido.');$categories=array_values(array_unique(array_filter(array_map('intval',(array)($input['category_ids']??[])))));
  $this->db->beginTransaction();try{
   if($id){$s=$this->db->prepare('UPDATE packages SET name=?,description=?,duration_days=?,credit_cost=?,max_connections=?,reseller_enabled=?,is_trial=?,active=? WHERE id=?');$s->execute([$name,$description?:null,$days?:null,$cost,$connections,isset($input['reseller_enabled']),isset($input['is_trial']),isset($input['active']),$id]);if(!$s->rowCount()&&!$this->exists($id))throw new RuntimeException('Paquete no encontrado.');}
   else{$this->db->prepare('INSERT INTO packages(name,description,duration_days,credit_cost,max_connections,reseller_enabled,is_trial,active) VALUES(?,?,?,?,?,?,?,?)')->execute([$name,$description?:null,$days?:null,$cost,$connections,isset($input['reseller_enabled']),isset($input['is_trial']),isset($input['active'])]);$id=(int)$this->db->lastInsertId();}
   $this->db->prepare('DELETE FROM package_categories WHERE package_id=?')->execute([$id]);$insert=$this->db->prepare('INSERT INTO package_categories(package_id,category_id) VALUES(?,?)');foreach($categories as $category)$insert->execute([$id,$category]);
   $this->db->prepare('DELETE FROM package_content_items WHERE package_id=?')->execute([$id]);$this->db->prepare('INSERT IGNORE INTO package_content_items(package_id,content_item_id) SELECT ?,id FROM content_items WHERE category_id IN (SELECT category_id FROM package_categories WHERE package_id=?)')->execute([$id,$id]);
   $this->db->commit();$this->audit->record('package.saved','package',$id,['name'=>$name,'categories'=>count($categories),'duration_days'=>$days,'credit_cost'=>$cost]);return $id;
  }catch(Throwable $e){if($this->db->inTransaction())$this->db->rollBack();throw $e;}
 }
 public function toggle(int $id):void{$this->db->prepare('UPDATE packages SET active=NOT active WHERE id=?')->execute([$id]);$this->audit->record('package.status_toggled','package',$id,[]);}
 private function exists(int $id):bool{$s=$this->db->prepare('SELECT 1 FROM packages WHERE id=?');$s->execute([$id]);return (bool)$s->fetchColumn();}
}
