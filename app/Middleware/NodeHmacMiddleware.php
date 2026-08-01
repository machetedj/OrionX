<?php
declare(strict_types=1);
namespace App\Middleware;
use App\Core\{Request,Response};use App\Security\DeviceCredentialCipher;use PDO;use Predis\Client as RedisClient;use Throwable;
final readonly class NodeHmacMiddleware
{
 public function __construct(private PDO $db,private RedisClient $redis,private DeviceCredentialCipher $cipher){}
 public function handle(Request $r):void
 {
  if(($_ENV['APP_ENV']??'production')==='production'&&strtolower((string)($r->server['HTTPS']??''))!=='on'&&strtolower((string)$r->header('X-Forwarded-Proto'))!=='https')Response::json(['ok'=>false,'error'=>['code'=>'https_required','message'=>'La API interna requiere HTTPS.']],400);
  try{$key=(string)$r->header('X-Node-Key');$timestamp=(string)$r->header('X-Node-Timestamp');$nonce=(string)$r->header('X-Node-Nonce');$signature=(string)$r->header('X-Node-Signature');if(!preg_match('/^[a-f0-9]{16}$/',$key)||!ctype_digit($timestamp)||abs(time()-(int)$timestamp)>60||!preg_match('/^[a-f0-9]{32}$/',$nonce)||!preg_match('/^[a-f0-9]{64}$/',$signature))throw new \RuntimeException('Firma interna inválida.');
   $s=$this->db->prepare('SELECT id,api_secret_ciphertext,allowed_ips,status FROM servers WHERE api_key_id=?');$s->execute([$key]);$server=$s->fetch()?:throw new \RuntimeException('Nodo desconocido.');if($server['status']==='maintenance'||empty($server['api_secret_ciphertext']))throw new \RuntimeException('Nodo no autorizado.');$allowed=json_decode($server['allowed_ips']?:'[]',true);$ip=$r->server['REMOTE_ADDR']??'';if($allowed&&!in_array($ip,$allowed,true))throw new \RuntimeException('IP del nodo no permitida.');
   $claimed=$this->redis->set('node:nonce:'.$key.':'.$nonce,'1','EX',120,'NX');if($claimed===null)throw new \RuntimeException('Nonce repetido.');$canonical=$timestamp."\n".$nonce."\n".$r->method."\n".$r->path."\n".hash('sha256',$r->rawBody);$expected=hash_hmac('sha256',$canonical,$this->cipher->decrypt($server['api_secret_ciphertext']));if(!hash_equals($expected,$signature))throw new \RuntimeException('Firma interna incorrecta.');$r->setAttribute('node_id',(int)$server['id']);
  }catch(Throwable $e){Response::json(['ok'=>false,'error'=>['code'=>'node_unauthorized','message'=>$e->getMessage()]],401);}
 }
}
