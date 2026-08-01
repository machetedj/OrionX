<?php
declare(strict_types=1);
namespace App\Controllers;
use App\Core\{Csrf,Request,Response,View};use App\Repositories\MediaLibraryRepository;use App\Security\Auth;use App\Services\MediaLibraryService;use Throwable;
final readonly class MediaLibraryController
{
 public function __construct(private View $view,private MediaLibraryRepository $repo,private MediaLibraryService $service,private Csrf $csrf){}
 public function index(Request $r):void{$this->allow();$this->view->render('libraries/index',['title'=>'Bibliotecas y carga masiva','libraries'=>$this->repo->libraries(),'servers'=>$this->repo->servers(),'jobs'=>$this->repo->jobs(),'conflicts'=>$this->repo->conflicts(),'csrf'=>$this->csrf]);}
 public function store(Request $r):void{$this->allow();$this->attempt(fn()=> $this->service->create($r->input));}
 public function scan(Request $r):void{$this->allow();$this->attempt(fn()=> $this->service->scan($r->int('library_id'),$r->string('type')));}
 private function allow():void{if(!Auth::can('storage.manage')){http_response_code(403);exit('Sin permiso');}}
 private function attempt(callable $fn):never{try{$fn();Response::redirect('/libraries');}catch(Throwable $e){http_response_code(422);exit(htmlspecialchars($e->getMessage(),ENT_QUOTES,'UTF-8'));}}
}
