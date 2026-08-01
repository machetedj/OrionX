<?php
declare(strict_types=1);
namespace App\Services;
use App\Repositories\AuditRepository;
use App\Security\{Auth,DeviceCredentialCipher};
use PDO;use RuntimeException;
final readonly class MagDeviceService
{
 public function __construct(private PDO $db,private DeviceCredentialCipher $cipher,private AuditRepository $audit){}
 public function create(int $accountId,string $mac):int{$mac=$this->normalize($mac);$exists=$this->db->prepare('SELECT 1 FROM end_user_accounts WHERE id=? AND deleted_at IS NULL');$exists->execute([$accountId]);if(!$exists->fetchColumn())throw new RuntimeException('Cuenta no encontrada.');$s=$this->db->prepare('INSERT INTO mag_devices(account_id,mac_hash,mac_ciphertext,created_by) VALUES(?,?,?,?)');$s->execute([$accountId,$this->hash($mac),$this->cipher->encrypt($mac),Auth::id()??0]);$id=(int)$this->db->lastInsertId();$this->audit->record('mag.created','mag_device',$id,['account_id'=>$accountId,'mac_suffix'=>substr($mac,-5)]);return $id;}
 public function status(int $id,string $status):void{if(!in_array($status,['active','blocked'],true))throw new RuntimeException('Estado inválido.');$this->db->prepare('UPDATE mag_devices SET status=?,token_hash=NULL,token_expires_at=NULL WHERE id=?')->execute([$status,$id]);$this->audit->record('mag.status_changed','mag_device',$id,['status'=>$status]);}
 public function reveal(int $id):string{$s=$this->db->prepare('SELECT mac_ciphertext FROM mag_devices WHERE id=?');$s->execute([$id]);$value=$s->fetchColumn();if(!$value)throw new RuntimeException('Dispositivo no encontrado.');$this->audit->record('mag.mac_revealed','mag_device',$id);return $this->cipher->decrypt($value);}
 public function handshake(string $mac,?string $ip,?string $agent):array{$mac=$this->normalize($mac);$s=$this->db->prepare("SELECT m.id,m.account_id,m.status,a.status account_status,a.expires_at FROM mag_devices m JOIN end_user_accounts a ON a.id=m.account_id AND a.deleted_at IS NULL WHERE m.mac_hash=?");$s->execute([$this->hash($mac)]);$d=$s->fetch();if(!$d||$d['status']!=='active'||$d['account_status']!=='active'||($d['expires_at']&&strtotime($d['expires_at'])<=time()))throw new RuntimeException('Dispositivo no autorizado.');$token=rtrim(strtr(base64_encode(random_bytes(32)),'+/','-_'),'=');$this->db->prepare('UPDATE mag_devices SET token_hash=?,token_expires_at=DATE_ADD(NOW(),INTERVAL 1 HOUR),last_ip=?,last_user_agent=?,last_seen_at=NOW() WHERE id=?')->execute([hash('sha256',$token),$ip,substr((string)$agent,0,255),$d['id']]);return ['token'=>$token,'account_id'=>(int)$d['account_id']];}
 public function authenticate(string $token):array{$s=$this->db->prepare("SELECT m.*,a.username,a.status account_status,a.expires_at,a.max_connections FROM mag_devices m JOIN end_user_accounts a ON a.id=m.account_id WHERE m.token_hash=? AND m.token_expires_at>NOW() AND m.status='active'");$s->execute([hash('sha256',$token)]);$d=$s->fetch();if(!$d||$d['account_status']!=='active'||($d['expires_at']&&strtotime($d['expires_at'])<=time()))throw new RuntimeException('Token MAG inválido.');return $d;}
 private function normalize(string $mac):string{$raw=strtoupper(preg_replace('/[^A-Fa-f0-9]/','',$mac)??'');if(!preg_match('/^[A-F0-9]{12}$/',$raw))throw new RuntimeException('MAC inválida.');return implode(':',str_split($raw,2));}
 private function hash(string $mac):string{return hash_hmac('sha256',$mac,(string)$_ENV['APP_KEY']);}
}
