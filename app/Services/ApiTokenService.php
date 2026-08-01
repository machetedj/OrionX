<?php
declare(strict_types=1);
namespace App\Services;
use PDO;use RuntimeException;
final readonly class ApiTokenService
{
 public function __construct(private PDO $db){}
 public function issue(string $audience,array $scopes,int $days,?int $userId=null,?int $resellerId=null,?int $accountId=null):string
 {
  if(!in_array($audience,['admin','reseller','device'],true)||$days<1||$days>365)throw new RuntimeException('Parámetros de token inválidos.');$scopes=array_values(array_unique(array_filter($scopes,fn($s)=>is_string($s)&&preg_match('/^[a-z][a-z0-9._:-]{1,80}$/',$s))));if(!$scopes)throw new RuntimeException('Se requiere al menos un scope.');$id=bin2hex(random_bytes(8));$secret=$this->encode(random_bytes(32));$hash=hash_hmac('sha256',$id.'.'.$secret,(string)$_ENV['APP_KEY']);$s=$this->db->prepare('INSERT INTO api_access_tokens(token_id,token_hash,audience,user_id,reseller_id,account_id,scopes,expires_at) VALUES(?,?,?,?,?,?,?,DATE_ADD(NOW(),INTERVAL ? DAY))');$s->execute([$id,$hash,$audience,$userId,$resellerId,$accountId,json_encode($scopes,JSON_THROW_ON_ERROR),$days]);return $id.'.'.$secret;
 }
 public function authenticate(string $token):array
 {
  if(!preg_match('/^([a-f0-9]{16})\.([A-Za-z0-9_-]{40,60})$/',$token,$m))throw new RuntimeException('Token de acceso inválido.');$s=$this->db->prepare('SELECT * FROM api_access_tokens WHERE token_id=? AND revoked_at IS NULL AND expires_at>NOW()');$s->execute([$m[1]]);$row=$s->fetch()?:throw new RuntimeException('Token revocado o vencido.');$hash=hash_hmac('sha256',$token,(string)$_ENV['APP_KEY']);if(!hash_equals($row['token_hash'],$hash))throw new RuntimeException('Token de acceso inválido.');$this->db->prepare('UPDATE api_access_tokens SET last_used_at=NOW() WHERE id=?')->execute([$row['id']]);$row['scopes']=json_decode($row['scopes'],true,32,JSON_THROW_ON_ERROR);return $row;
 }
 private function encode(string $value):string{return rtrim(strtr(base64_encode($value),'+/','-_'),'=');}
}
