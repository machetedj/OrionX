<?php
declare(strict_types=1);
namespace App\Controllers;
use App\Core\{Csrf,Request,Response,View};use App\Repositories\XuiImportRepository;use App\Security\Auth;use App\Services\XuiImportService;use Throwable;
final readonly class XuiImportController
{
 public function __construct(private View $view,private XuiImportRepository $repo,private XuiImportService $service,private Csrf $csrf){}
 public function index(Request $r):void{$this->allow();$this->view->render('xui/index',['title'=>'Importar XUI One','connections'=>$this->repo->connections(),'imports'=>$this->repo->imports(),'uploads'=>$this->repo->uploads(),'conversions'=>$this->repo->conversions(),'csrf'=>$this->csrf]);}
 public function store(Request $r):void{$this->allow();$this->attempt(fn()=> $this->service->save($r->input));}
 public function test(Request $r):void{$this->allow();$this->attempt(fn()=> $this->service->test($r->int('id')));}
 public function run(Request $r):void{$this->allow();$this->attempt(fn()=> $this->service->queue($r->int('id'),filter_var($r->input['replace']??false,FILTER_VALIDATE_BOOL)));}
 public function upload(Request $r):void{$this->allow();$this->attempt(function():void{set_time_limit(0);ignore_user_abort(true);$id=$this->service->upload($_FILES['sql_dump']??[]);$this->service->runUpload($id);});}
 public function processUpload(Request $r):void{$this->allow();$this->attempt(function()use($r):void{set_time_limit(0);ignore_user_abort(true);$this->service->runUpload($r->int('id'));});}
 public function detail(Request $r):void{$this->allow();Response::json(['tables'=>$this->repo->tables($r->int('id'))]);}
 public function conflicts(Request $r):void{$this->allow();Response::json(['conflicts'=>$this->repo->conflicts($r->int('id'))]);}
 private function attempt(callable $fn):never{try{$fn();Response::redirect('/xui-import');}catch(Throwable $e){http_response_code(422);exit(htmlspecialchars($e->getMessage(),ENT_QUOTES,'UTF-8'));}}
 private function allow():void{if(!Auth::can('xui.manage')){http_response_code(403);exit('Sin permiso');}}
}
