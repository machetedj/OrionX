<?php
declare(strict_types=1);
namespace App\Controllers;
use App\Core\{Csrf,Request,Response,View};use App\Repositories\LiveChannelRepository;use App\Security\Auth;use App\Services\LiveChannelService;use Throwable;
final readonly class LiveChannelController
{
 public function __construct(private View $view,private LiveChannelRepository $repo,private LiveChannelService $service,private Csrf $csrf){}
 public function index(Request $r):void{$this->allow();$channels=$this->repo->all();foreach($channels as &$channel)$channel['sources']=$this->repo->sources((int)$channel['id']);$this->view->render('live/index',['title'=>'Canales en vivo','channels'=>$channels,'categories'=>$this->repo->categories(),'servers'=>$this->repo->servers(),'csrf'=>$this->csrf]);}
 public function store(Request $r):void{$this->allow();$this->attempt(fn()=> $this->service->create($r->input));}
 public function source(Request $r):void{$this->allow();$this->attempt(fn()=> $this->service->addSource($r->int('channel_id'),$r->input));}
 private function allow():void{if(!Auth::can('streams.manage')){http_response_code(403);exit('Sin permiso');}}
 private function attempt(callable $fn):never{try{$fn();Response::redirect('/live');}catch(Throwable $e){http_response_code(422);exit(htmlspecialchars($e->getMessage(),ENT_QUOTES,'UTF-8'));}}
}
