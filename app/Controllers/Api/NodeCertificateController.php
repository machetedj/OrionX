<?php
declare(strict_types=1);
namespace App\Controllers\Api;
use App\Core\{Request,Response};use App\Services\CertificateService;use Throwable;
final class NodeCertificateController extends ApiController
{
 public function __construct(private readonly CertificateService $certificates){}
 public function pending(Request $r):void{try{$request=$this->certificates->claimForNode((int)$r->attribute('node_id'));$this->ok($request??['pending'=>false]);}catch(Throwable $e){Response::json(['ok'=>false,'error'=>['code'=>'certificate_error','message'=>$e->getMessage()]],422);}}
 public function result(Request $r):void{try{$this->certificates->nodeResult((int)$r->attribute('node_id'),$r->int('request_id'),filter_var($r->input['success']??false,FILTER_VALIDATE_BOOL),$r->string('certificate_path')?:null,$r->string('expires_at')?:null,$r->string('error')?:null);$this->ok(['accepted'=>true]);}catch(Throwable $e){Response::json(['ok'=>false,'error'=>['code'=>'certificate_error','message'=>$e->getMessage()]],422);}}
}
