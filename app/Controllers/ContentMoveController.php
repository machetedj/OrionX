<?php
declare(strict_types=1);
namespace App\Controllers;
use App\Core\{Csrf,Request,Response,View};use App\Repositories\ContentMoveRepository;use App\Security\Auth;use App\Services\ContentMoveService;use Throwable;
final readonly class ContentMoveController
{
 public function __construct(private View $view,private ContentMoveRepository $repo,private ContentMoveService $service,private Csrf $csrf){}
 public function index(Request $r):void{$this->allow();$counts=$this->repo->counts();$this->view->render('servers/move-content',['title'=>'Mover contenido entre servidores','servers'=>$this->repo->servers(),'counts'=>$counts,'result'=>$r->input,'csrf'=>$this->csrf]);}
 public function move(Request $r):void{$this->allow();try{$moved=$this->service->move($r->input);Response::redirect('/servers/move-content?live='.$moved['live'].'&movie='.$moved['movie'].'&series='.$moved['series']);}catch(Throwable $e){http_response_code(422);exit(htmlspecialchars($e->getMessage(),ENT_QUOTES,'UTF-8'));}}
 private function allow():void{if(!Auth::can('servers.manage')||!Auth::can('bulk.execute')){http_response_code(403);exit('Sin permiso para mover contenido');}}
}
