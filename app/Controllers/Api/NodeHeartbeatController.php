<?php
declare(strict_types=1);
namespace App\Controllers\Api;
use App\Core\{Request,Response};use App\Services\ServerHealthService;use Throwable;
final class NodeHeartbeatController extends ApiController
{
 public function __construct(private readonly ServerHealthService $health){}
 public function heartbeat(Request $r):void{try{$this->ok($this->health->heartbeat((int)$r->attribute('node_id'),$r->input));}catch(Throwable $e){Response::json(['ok'=>false,'error'=>['code'=>'invalid_heartbeat','message'=>$e->getMessage()]],422);}}
}
