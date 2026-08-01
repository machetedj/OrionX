<?php
declare(strict_types=1);namespace App\Controllers;use App\Core\{Csrf,Request,Response,View};use App\Repositories\SessionRepository;use App\Security\Auth;use App\Services\PlaybackControlService;use Throwable;
final readonly class SessionController{
 public function __construct(private View $view,private SessionRepository $repo,private PlaybackControlService $control,private Csrf $csrf){}
 public function index(Request $r):void{$this->allow('accounts.view');$f=['q'=>$r->string('q'),'type'=>$r->string('type'),'country'=>$r->string('country')];$this->view->render('sessions/index',['title'=>'Conexiones activas','sessions'=>$this->repo->active($f),'countries'=>$this->repo->countries(),'summary'=>$this->repo->summary(),'filters'=>$f,'csrf'=>$this->csrf]);}
 public function disconnect(Request $r):void{$this->allow('sessions.disconnect');$this->run(fn()=>$this->control->disconnect($r->string('id')));}
 public function killContent(Request $r):void{$this->allow('sessions.disconnect');$this->run(fn()=>$this->control->killContent($r->int('content_id')));}
 public function banLine(Request $r):void{$this->allow('accounts.update');$this->run(fn()=>$this->control->banLine($r->int('account_id')));}
 public function banIp(Request $r):void{$this->allow('security.manage');$this->run(fn()=>$this->control->banIp($r->string('ip')));}
 private function run(callable $fn):never{try{$fn();Response::redirect('/sessions');}catch(Throwable $e){http_response_code(422);exit(htmlspecialchars($e->getMessage(),ENT_QUOTES,'UTF-8'));}}
 private function allow(string $p):void{if(!Auth::can($p)){http_response_code(403);exit('Sin permiso');}}
}
