<?php
declare(strict_types=1);
namespace App\Middleware;
use App\Core\{Request,Response};use App\Services\ApiTokenService;use Predis\Client as RedisClient;use Throwable;
final readonly class ApiTokenMiddleware
{
 public function __construct(private ApiTokenService $tokens,private RedisClient $redis){}
 public function handle(Request $request):void
 {
  try{$authorization=(string)$request->header('Authorization');if(!preg_match('/^Bearer\s+(.+)$/i',$authorization,$m))throw new \RuntimeException('Falta el token Bearer.');$token=$this->tokens->authenticate(trim($m[1]));$key='api:rate:'.$token['token_id'].':'.time();$count=(int)$this->redis->incr($key);if($count===1)$this->redis->expire($key,2);if($count>60)Response::json(['ok'=>false,'error'=>['code'=>'rate_limited','message'=>'Demasiadas solicitudes.']],429);$request->setAttribute('api_token',$token);$request->setAttribute('correlation_id',bin2hex(random_bytes(16)));}catch(Throwable $e){Response::json(['ok'=>false,'error'=>['code'=>'unauthorized','message'=>$e->getMessage()]],401);}
 }
}
