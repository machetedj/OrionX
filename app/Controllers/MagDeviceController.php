<?php
declare(strict_types=1);
namespace App\Controllers;
use App\Core\{Csrf,Request,Response,View};use App\Repositories\MagDeviceRepository;use App\Security\Auth;use App\Services\MagDeviceService;use Throwable;
final readonly class MagDeviceController
{
 public function __construct(private View $view,private MagDeviceRepository $repo,private MagDeviceService $service,private Csrf $csrf){}
 public function index(Request $r):void{$this->allow();$this->view->render('mag/index',['title'=>'Dispositivos MAG','devices'=>$this->repo->all(),'accounts'=>$this->repo->accounts(),'csrf'=>$this->csrf]);}
 public function store(Request $r):void{$this->allow();$this->attempt(fn()=> $this->service->create($r->int('account_id'),$r->string('mac')));}
 public function status(Request $r):void{$this->allow();$this->attempt(fn()=> $this->service->status($r->int('id'),$r->string('status')));}
 public function reveal(Request $r):void{$this->allow();try{Response::json(['mac'=>$this->service->reveal($r->int('id'))]);}catch(Throwable $e){Response::json(['error'=>$e->getMessage()],422);}}
 private function attempt(callable $fn):never{try{$fn();Response::redirect('/mag-devices');}catch(Throwable $e){http_response_code(422);exit(htmlspecialchars($e->getMessage(),ENT_QUOTES,'UTF-8'));}}
 private function allow():void{if(!Auth::can('mag.manage')){http_response_code(403);exit('Sin permiso');}}
}
