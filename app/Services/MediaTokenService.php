<?php
declare(strict_types=1);
namespace App\Services;
use PDO;use RuntimeException;
final readonly class MediaTokenService
{
 public function __construct(private PDO $db){}
 public function issue(int $accountId,string $sessionId,string $publicFileId,?string $ip,?string $userAgent):string
 {
  if(!preg_match('/^[a-f0-9]{32}$/',$publicFileId)||!preg_match('/^[a-f0-9-]{36}$/i',$sessionId))throw new RuntimeException('Recurso o sesión inválidos.');$ttl=max(30,min(600,(int)($_ENV['MEDIA_TOKEN_TTL']??120)));$keyId=(string)($_ENV['MEDIA_SIGNING_KEY_ID']??'v1');
  $payload=['v'=>1,'kid'=>$keyId,'jti'=>bin2hex(random_bytes(16)),'sub'=>$accountId,'sid'=>$sessionId,'rid'=>$publicFileId,'exp'=>time()+$ttl,'ip'=>$ip,'uah'=>$userAgent?hash('sha256',$userAgent):null];$body=$this->encode(json_encode($payload,JSON_THROW_ON_ERROR));return $body.'.'.$this->encode(hash_hmac('sha256',$body,$this->key($keyId),true));
 }
 public function verify(string $token,?string $ip,?string $userAgent):array
 {
  $parts=explode('.',$token);if(count($parts)!==2)throw new RuntimeException('Token multimedia inválido.');[$body,$signature]=$parts;$payload=json_decode($this->decode($body),true,32,JSON_THROW_ON_ERROR);$expected=hash_hmac('sha256',$body,$this->key((string)($payload['kid']??'')),true);$provided=$this->decode($signature);if(!hash_equals($expected,$provided))throw new RuntimeException('Firma multimedia inválida.');
  if(($payload['v']??null)!==1||(int)($payload['exp']??0)<time())throw new RuntimeException('Token multimedia vencido o incompatible.');if(($payload['ip']??null)!==null&&!hash_equals((string)$payload['ip'],(string)$ip))throw new RuntimeException('IP no autorizada.');if(($payload['uah']??null)!==null&&!hash_equals((string)$payload['uah'],hash('sha256',(string)$userAgent)))throw new RuntimeException('User-Agent no autorizado.');
  $s=$this->db->prepare('SELECT 1 FROM revoked_media_tokens WHERE token_id=? AND expires_at>NOW()');$s->execute([$payload['jti']]);if($s->fetchColumn())throw new RuntimeException('Token revocado.');return $payload;
 }
 private function key(string $keyId):string{$configured=json_decode((string)($_ENV['MEDIA_SIGNING_KEYS']??'{}'),true);$current=(string)($_ENV['MEDIA_SIGNING_KEY_ID']??'v1');$encoded=is_array($configured)&&isset($configured[$keyId])?$configured[$keyId]:($keyId===$current?($_ENV['MEDIA_SIGNING_KEY']??''):null);if(!is_string($encoded))throw new RuntimeException('Clave multimedia desconocida.');$key=base64_decode($encoded,true);if($key===false||strlen($key)<32)throw new RuntimeException('MEDIA_SIGNING_KEY inválida.');return $key;}
 private function encode(string $data):string{return rtrim(strtr(base64_encode($data),'+/','-_'),'=');}
 private function decode(string $data):string{$padding=(4-strlen($data)%4)%4;$decoded=base64_decode(strtr($data,'-_','+/').str_repeat('=',$padding),true);if($decoded===false)throw new RuntimeException('Token mal codificado.');return $decoded;}
}
