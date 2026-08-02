<?php
declare(strict_types=1);
namespace App\Controllers;
use App\Core\{Csrf,Request,Response,View};use App\Repositories\WafRepository;use App\Security\Auth;use App\Services\WafService;use Throwable;
final readonly class WafController
{
 public function __construct(private View $view,private WafRepository $repo,private WafService $service,private Csrf $csrf){}
 public function index(Request $r):void{$this->allow();$this->view->render('security/waf',['title'=>'Web Application Firewall','csrf'=>$this->csrf]+$this->repo->data()+['notice'=>$r->input]);}
 public function settings(Request $r):void{$this->allow();$this->attempt(fn()=>$this->service->settings($r->input),'settings');}
 public function rule(Request $r):void{$this->allow();$this->attempt(fn()=>$this->service->addRule($r->input),'rule');}
 public function exclusion(Request $r):void{$this->allow();$this->attempt(fn()=>$this->service->addExclusion($r->input),'exclusion');}
 public function toggle(Request $r):void{$this->allow();$this->attempt(fn()=>$this->service->toggle($r->string('kind'),$r->int('id')),'toggle');}
 public function deploy(Request $r):void{$this->allow();$server=$r->int('server_id')?:null;$this->attempt(fn()=>$this->service->requestDeploy($server),'deploy');}
 public function import(Request $r):void{$this->allow();try{$file=$_FILES['log_file']??null;if(!$file||$file['error']!==UPLOAD_ERR_OK||!is_uploaded_file($file['tmp_name']))throw new \RuntimeException('Selecciona un archivo de logs válido.');$count=$this->service->importBarracuda($file['tmp_name']);Response::redirect('/security/waf?imported='.$count);}catch(Throwable $e){http_response_code(422);exit(htmlspecialchars($e->getMessage(),ENT_QUOTES,'UTF-8'));}}
 private function attempt(callable $fn,string $done):never{try{$fn();Response::redirect('/security/waf?done='.$done);}catch(Throwable $e){http_response_code(422);exit(htmlspecialchars($e->getMessage(),ENT_QUOTES,'UTF-8'));}}
 private function allow():void{if(!Auth::can('waf.manage')){http_response_code(403);exit('Sin permiso para administrar WAF');}}
}
