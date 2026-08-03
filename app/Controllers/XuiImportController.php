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
 public function upload(Request $r):void{$this->allow();try{$id=$this->service->upload($_FILES['sql_dump']??[]);}catch(Throwable $e){http_response_code(422);exit(htmlspecialchars($e->getMessage(),ENT_QUOTES,'UTF-8'));}$this->finishBrowserRequest();$this->processInBackground($id);}
 public function processUpload(Request $r):void{$this->allow();$id=$r->int('id');$this->finishBrowserRequest();$this->processInBackground($id);}
 public function convertLines(Request $r):void{$this->allow();$id=$r->int('id');$this->finishBrowserRequest();try{$this->service->convertUploadedLines($id);}catch(Throwable $e){error_log('XUI lines #'.$id.': '.$e->getMessage());}}
 public function detail(Request $r):void{$this->allow();Response::json(['tables'=>$this->repo->tables($r->int('id'))]);}
 public function conflicts(Request $r):void{$this->allow();Response::json(['conflicts'=>$this->repo->conflicts($r->int('id'))]);}
 private function attempt(callable $fn):never{try{$fn();Response::redirect('/xui-import');}catch(Throwable $e){http_response_code(422);exit(htmlspecialchars($e->getMessage(),ENT_QUOTES,'UTF-8'));}}
 private function finishBrowserRequest():void{ignore_user_abort(true);set_time_limit(0);if(session_status()===PHP_SESSION_ACTIVE)session_write_close();$body='Importación iniciada';header('Location: /xui-import',true,303);header('Content-Type: text/plain; charset=UTF-8');header('Content-Length: '.strlen($body));header('Connection: close');echo $body;if(function_exists('fastcgi_finish_request'))fastcgi_finish_request();else{while(ob_get_level()>0)ob_end_flush();flush();}}
 private function processInBackground(int $id):void{try{$this->service->runUpload($id);}catch(Throwable $e){error_log('XUI upload #'.$id.': '.$e->getMessage());}}
 private function allow():void{if(!Auth::can('xui.manage')){http_response_code(403);exit('Sin permiso');}}
}
