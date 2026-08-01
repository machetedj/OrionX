<?php
declare(strict_types=1);
namespace App\Services;
use App\Repositories\AuditRepository;use App\Security\{Auth,DeviceCredentialCipher};use PDO;use RuntimeException;
final readonly class ProxyService
{
 public function __construct(private PDO $db,private DeviceCredentialCipher $cipher,private AuditRepository $audit){}
 public function create(array $in):int
 {
  $name=trim((string)($in['name']??''));$scheme=(string)($in['scheme']??'http');$host=strtolower(trim((string)($in['host']??'')));$port=(int)($in['port']??0);$server=(int)($in['server_id']??0)?:null;$username=trim((string)($in['username']??''))?:null;$password=(string)($in['password']??'');if($name===''||strlen($name)>150)throw new RuntimeException('Escribe un nombre válido.');if(!in_array($scheme,['http','https','socks5'],true))throw new RuntimeException('Tipo de proxy no válido.');if(!$this->validHost($host))throw new RuntimeException('Host o dirección IP no válida.');if($port<1||$port>65535)throw new RuntimeException('Puerto no válido.');if($server){$q=$this->db->prepare('SELECT COUNT(*) FROM servers WHERE id=?');$q->execute([$server]);if(!(int)$q->fetchColumn())throw new RuntimeException('Servidor no encontrado.');}$q=$this->db->prepare('INSERT INTO server_proxies(server_id,name,scheme,host,port,username,password_ciphertext,active,created_by) VALUES(?,?,?,?,?,?,?,?,?)');$q->execute([$server,$name,$scheme,$host,$port,$username,$password!==''?$this->cipher->encrypt($password):null,isset($in['active'])?1:0,Auth::id()]);$id=(int)$this->db->lastInsertId();$this->audit->record('proxy.created','server_proxy',$id,['server_id'=>$server,'scheme'=>$scheme,'host'=>$host,'port'=>$port,'has_credentials'=>$username!==null||$password!=='']);return $id;
 }
 public function status(int $id,bool $active):void{$q=$this->db->prepare('UPDATE server_proxies SET active=? WHERE id=?');$q->execute([$active?1:0,$id]);if(!$q->rowCount())throw new RuntimeException('Proxy no encontrado.');$this->audit->record('proxy.status_changed','server_proxy',$id,['active'=>$active]);}
 public function delete(int $id):void{$q=$this->db->prepare('DELETE FROM server_proxies WHERE id=?');$q->execute([$id]);if(!$q->rowCount())throw new RuntimeException('Proxy no encontrado.');$this->audit->record('proxy.deleted','server_proxy',$id);}
 private function validHost(string $host):bool{return filter_var($host,FILTER_VALIDATE_IP)!==false||preg_match('/^(?=.{1,253}$)(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)*[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?$/',$host)===1;}
}
