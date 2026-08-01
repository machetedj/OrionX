<?php
declare(strict_types=1);
namespace App\Controllers;
use App\Core\{Csrf,Request,Response,View};use App\Repositories\IpBlockRepository;use App\Security\Auth;use App\Services\IpBlockService;use Throwable;
final readonly class SecurityController
{
 public function __construct(private View $view,private IpBlockRepository $repo,private IpBlockService $service,private Csrf $csrf){}
 public function ips(Request $r):void{$this->allow();$this->view->render('security/ip-blocks',['title'=>'Bloqueo de IP','blocks'=>$this->repo->blocks(),'suspects'=>$this->repo->suspects(),'currentIp'=>(string)($r->server['REMOTE_ADDR']??''),'csrf'=>$this->csrf]);}
 public function add(Request $r):void{$this->allow();$this->attempt(fn()=> $this->service->add($r->string('ip'),$r->string('reason'),$r->int('hours'),(string)($r->server['REMOTE_ADDR']??'')));}
 public function remove(Request $r):void{$this->allow();$this->attempt(fn()=> $this->service->remove($r->int('id')));}
 private function attempt(callable $fn):never{try{$fn();Response::redirect('/security/ip-blocks');}catch(Throwable $e){http_response_code(422);exit(htmlspecialchars($e->getMessage(),ENT_QUOTES,'UTF-8'));}}
 private function allow():void{if(!Auth::can('security.manage')){http_response_code(403);exit('Sin permiso');}}
}
