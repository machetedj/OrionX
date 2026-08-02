<?php
declare(strict_types=1);
namespace App\Controllers;
use App\Core\{Csrf,Request,Response,View}; use App\Repositories\ResourceRepository; use App\Security\Auth; use App\Services\ServerHealthService;
final readonly class ResourceController
{
 public function __construct(private View $view,private ResourceRepository $repo,private Csrf $csrf,private ServerHealthService $health){}
 public function categories(Request $r):void{$this->show('categories','Categorías');} public function servers(Request $r):void{$this->deny('servers.manage');$this->view->render('resources/servers',['title'=>'Servidores','servers'=>$this->repo->serverOverview(),'csrf'=>$this->csrf]);} public function resellers(Request $r):void{$this->show('resellers','Revendedores');}
 public function storeCategory(Request $r):void{$this->store('categories',['name'=>$r->string('name'),'type'=>$r->string('type'),'active'=>1],'/categories');}
 public function storeServer(Request $r):void{$this->store('servers',['name'=>$r->string('name'),'public_ip'=>$r->string('public_ip'),'private_ip'=>$r->string('private_ip'),'region'=>$r->string('region'),'status'=>'pending','max_capacity'=>max(1,$r->int('max_capacity')),'weight'=>max(1,$r->int('weight')),'priority'=>max(1,$r->int('priority'))],'/servers');}
 public function serverMode(Request $r):void{$this->deny('servers.manage');$this->health->setMode($r->int('id'),$r->string('mode'));Response::redirect('/servers');}
 private function show(string $resource,string $title):void{$this->deny($resource.'.manage');$this->view->render('resources/index',['title'=>$title,'resource'=>$resource,'items'=>$this->repo->all($resource),'csrf'=>$this->csrf]);}
 private function store(string $resource,array $data,string $to):void{$this->deny($resource.'.manage');$this->repo->insert($resource,$data);Response::redirect($to);}
 private function deny(string $p):void{if(!Auth::can($p)){http_response_code(403);exit('Sin permiso');}}
}
