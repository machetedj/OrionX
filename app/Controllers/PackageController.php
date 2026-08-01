<?php
declare(strict_types=1);
namespace App\Controllers;
use App\Core\{Csrf,Request,Response,View};use App\Repositories\PackageRepository;use App\Security\Auth;use App\Services\PackageService;use Throwable;
final readonly class PackageController
{
 public function __construct(private View $view,private PackageRepository $repo,private PackageService $service,private Csrf $csrf){}
 public function index(Request $r):void{$this->allow();$this->view->render('packages/index',['title'=>'Packages','packages'=>$this->repo->all(),'categories'=>$this->repo->categories(),'categoryMap'=>$this->repo->categoryMap(),'csrf'=>$this->csrf]);}
 public function save(Request $r):void{$this->allow();$this->attempt(fn()=> $this->service->save($r->input));}
 public function toggle(Request $r):void{$this->allow();$this->attempt(fn()=> $this->service->toggle($r->int('id')));}
 private function attempt(callable $fn):never{try{$fn();Response::redirect('/packages');}catch(Throwable $e){http_response_code(422);exit(htmlspecialchars($e->getMessage(),ENT_QUOTES,'UTF-8'));}}
 private function allow():void{if(!Auth::can('packages.manage')){http_response_code(403);exit('Sin permiso');}}
}
