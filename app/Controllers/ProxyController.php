<?php
declare(strict_types=1);
namespace App\Controllers;
use App\Core\{Csrf,Request,Response,View};use App\Repositories\ProxyRepository;use App\Security\Auth;use App\Services\ProxyService;use Throwable;
final readonly class ProxyController
{
 public function __construct(private View $view,private ProxyRepository $repo,private ProxyService $service,private Csrf $csrf){}
 public function index(Request $r):void{$this->allow();$this->view->render('servers/proxies',['title'=>'Proxies de servidores','proxies'=>$this->repo->all(),'servers'=>$this->repo->servers(),'csrf'=>$this->csrf]);}
 public function store(Request $r):void{$this->allow();$this->attempt(fn()=>$this->service->create($r->input));}
 public function status(Request $r):void{$this->allow();$this->attempt(fn()=>$this->service->status($r->int('id'),$r->string('active')==='1'));}
 public function delete(Request $r):void{$this->allow();$this->attempt(fn()=>$this->service->delete($r->int('id')));}
 private function attempt(callable $fn):never{try{$fn();Response::redirect('/servers/proxies');}catch(Throwable $e){http_response_code(422);exit(htmlspecialchars($e->getMessage(),ENT_QUOTES,'UTF-8'));}}
 private function allow():void{if(!Auth::can('servers.manage')){http_response_code(403);exit('Sin permiso para administrar proxies');}}
}
