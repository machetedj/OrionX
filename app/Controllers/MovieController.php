<?php
declare(strict_types=1);
namespace App\Controllers;
use App\Core\{Csrf,Request,Response,View};use App\Repositories\MovieRepository;use App\Security\Auth;use App\Services\{MovieService,TmdbService};use Throwable;
final readonly class MovieController
{
 public function __construct(private View $view,private MovieRepository $repo,private MovieService $service,private TmdbService $tmdb,private Csrf $csrf){}
 public function index(Request $r):void{$this->allow();$listing=$this->repo->browse($r->input);$this->view->render('movies/index',['title'=>'Películas','movies'=>$listing['items'],'pagination'=>$listing,'filters'=>$r->input,'servers'=>$this->repo->servers(),'categories'=>$this->repo->categories(),'libraries'=>$this->repo->libraries(),'packages'=>$this->repo->packages(),'csrf'=>$this->csrf]);}
 public function store(Request $r):void{$this->allow();try{$this->service->create($r->input);Response::redirect('/movies');}catch(Throwable $e){http_response_code(422);exit(htmlspecialchars($e->getMessage(),ENT_QUOTES,'UTF-8'));}}
 public function tmdb(Request $r):void{$this->allow();try{Response::json(['ok'=>true,'data'=>$this->tmdb->search('movie',$r->string('title'),$r->int('year')?:null,$r->string('language')?:'es-ES')]);}catch(Throwable $e){Response::json(['ok'=>false,'error'=>$e->getMessage()],422);}}
 public function tmdbDetails(Request $r):void{$this->allow();try{Response::json(['ok'=>true,'data'=>$this->tmdb->details('movie',$r->int('id'),$r->string('language')?:'es-ES')]);}catch(Throwable $e){Response::json(['ok'=>false,'error'=>$e->getMessage()],422);}}
 private function allow():void{if(!Auth::can('movies.manage')){http_response_code(403);exit('Sin permiso');}}
}
