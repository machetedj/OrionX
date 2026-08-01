<?php
declare(strict_types=1);
namespace App\Controllers;
use App\Core\{Csrf,Request,Response,View};use App\Repositories\DeploymentRepository;use App\Security\Auth;use App\Services\DeploymentService;use Throwable;
final readonly class DeploymentController
{
 public function __construct(private View $view,private DeploymentRepository $repo,private DeploymentService $service,private Csrf $csrf){}
 public function index(Request $r):void{$this->allow();$this->view->render('deployments/index',['title'=>'Instalar balanceadores','servers'=>$this->repo->servers(),'history'=>$this->repo->history(),'csrf'=>$this->csrf]);}
 public function fingerprint(Request $r):void{$this->allow();try{Response::json(['ok'=>true,'fingerprint'=>$this->service->fingerprint($r->int('server_id'),$r->int('port')?:22)]);}catch(Throwable $e){Response::json(['ok'=>false,'error'=>$e->getMessage()],422);}}
 public function credentials(Request $r):void{$this->allow();$this->attempt(fn()=> $this->service->saveCredentials($r->int('server_id'),$r->int('port')?:22,$r->string('ssh_user'),$r->string('fingerprint'),(string)($r->input['private_key']??''),(string)($r->input['ssh_password']??'')));}
 public function deploy(Request $r):void{$this->allow();$this->attempt(fn()=> $this->service->request($r->int('server_id'),$r->string('type')));}
 public function cutover(Request $r):void{$this->allow();$this->attempt(fn()=> $this->service->authorizeCutover($r->int('server_id')));}
 private function attempt(callable $fn):never{try{$fn();Response::redirect('/deployments');}catch(Throwable $e){http_response_code(422);exit(htmlspecialchars($e->getMessage(),ENT_QUOTES,'UTF-8'));}}
 private function allow():void{if(!Auth::can('servers.deploy')){http_response_code(403);exit('Sin permiso');}}
}
