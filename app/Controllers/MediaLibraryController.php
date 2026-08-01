<?php
declare(strict_types=1);
namespace App\Controllers;
use App\Core\{Csrf,Request,Response,View};use App\Repositories\MediaLibraryRepository;use App\Security\Auth;use App\Services\{MediaLibraryService,RemoteMediaService,WatchFolderService};use Throwable;
final readonly class MediaLibraryController
{
 public function __construct(private View $view,private MediaLibraryRepository $repo,private MediaLibraryService $service,private RemoteMediaService $remote,private WatchFolderService $watch,private Csrf $csrf){}
 public function index(Request $r):void{$this->allow();$this->view->render('libraries/index',['title'=>'Bibliotecas y carga masiva','libraries'=>$this->repo->libraries(),'watchFolders'=>$this->repo->watchFolders(),'servers'=>$this->repo->servers(),'jobs'=>$this->repo->jobs(),'conflicts'=>$this->repo->conflicts(),'remoteRuns'=>$this->repo->remoteRuns(),'links'=>$this->repo->links(),'csrf'=>$this->csrf]);}
 public function store(Request $r):void{$this->allow();$this->attempt(fn()=> $this->service->create($r->input));}
 public function scan(Request $r):void{$this->allow();$this->attempt(fn()=> $this->service->scan($r->int('library_id'),$r->string('type')));}
 public function remote(Request $r):void{$this->allow();$this->attempt(fn()=> $this->remote->request($r->int('library_id'),$r->string('operation'),$r->int('target_server_id')?:null));}
 public function watchStore(Request $r):void{$this->allow();$this->attempt(fn()=> $this->watch->create($r->input));}
 public function watchRun(Request $r):void{$this->allow();$this->attempt(fn()=> $this->watch->run($r->int('id')));}
 public function watchDisable(Request $r):void{$this->allow();$this->attempt(fn()=> $this->watch->disable($r->int('id')));}
 private function allow():void{if(!Auth::can('storage.manage')){http_response_code(403);exit('Sin permiso');}}
 private function attempt(callable $fn):never{try{$fn();Response::redirect('/libraries');}catch(Throwable $e){http_response_code(422);exit(htmlspecialchars($e->getMessage(),ENT_QUOTES,'UTF-8'));}}
}
