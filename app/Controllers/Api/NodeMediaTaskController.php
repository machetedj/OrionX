<?php
declare(strict_types=1);
namespace App\Controllers\Api;
use App\Core\{Request,Response};use App\Services\NodeMediaTaskService;use Throwable;
final class NodeMediaTaskController extends ApiController
{
 public function __construct(private readonly NodeMediaTaskService $tasks){}
 public function pending(Request $r):void{try{$task=$this->tasks->claim((int)$r->attribute('node_id'));$this->ok($task??['pending'=>false]);}catch(Throwable $e){Response::json(['ok'=>false,'error'=>['code'=>'node_task_error','message'=>$e->getMessage()]],422);}}
 public function result(Request $r):void{try{$this->tasks->result((int)$r->attribute('node_id'),$r->string('task_id'),filter_var($r->input['success']??false,FILTER_VALIDATE_BOOL),is_array($r->input['result']??null)?$r->input['result']:[],$r->string('error')?:null);$this->ok(['accepted'=>true]);}catch(Throwable $e){Response::json(['ok'=>false,'error'=>['code'=>'node_task_error','message'=>$e->getMessage()]],422);}}
}
