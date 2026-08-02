<?php
declare(strict_types=1);
namespace App\Controllers;
use App\Core\{Csrf,Request,Response,View};use App\Repositories\ResellerRepository;use App\Security\Auth;use App\Services\ResellerAccountService;use Throwable;
final readonly class ResellerPortalController
{
 public function __construct(private View $view,private ResellerRepository $repo,private ResellerAccountService $service,private Csrf $csrf){}
 public function index(Request $r):void{$profile=$this->repo->byUser(Auth::id()??0);if(!$profile)Response::redirect('/reseller/login');$this->view->render('reseller/dashboard',['title'=>$profile['brand_name']?:'Panel de revendedor','profile'=>$profile,'summary'=>$this->repo->summary((int)$profile['id']),'accounts'=>$this->repo->accounts((int)$profile['id']),'movements'=>$this->repo->movements((int)$profile['id']),'packages'=>$this->repo->packages((int)$profile['id']),'csrf'=>$this->csrf]);}
 public function create(Request $r):void{$this->attempt(fn()=> $this->service->create($r->input));}
 public function renew(Request $r):void{$this->attempt(fn()=> $this->service->renew($r->int('id'),$r->int('package_id')));}
 public function suspend(Request $r):void{$this->attempt(fn()=> $this->service->suspend($r->int('id')));}
 private function attempt(callable $action):never{try{$action();Response::redirect('/reseller');}catch(Throwable $e){http_response_code(422);exit(htmlspecialchars($e->getMessage(),ENT_QUOTES,'UTF-8'));}}
}
