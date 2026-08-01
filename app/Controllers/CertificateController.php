<?php
declare(strict_types=1);
namespace App\Controllers;
use App\Core\{Csrf,Request,Response,View};use App\Repositories\CertificateRepository;use App\Security\Auth;use App\Services\CertificateService;use Throwable;
final readonly class CertificateController
{
 public function __construct(private View $view,private CertificateRepository $repo,private CertificateService $service,private Csrf $csrf){}
 public function index(Request $r):void{$this->allow();$this->view->render('certificates/index',['title'=>'Certificados TLS','requests'=>$this->repo->all(),'servers'=>$this->repo->servers(),'csrf'=>$this->csrf]);}
 public function store(Request $r):void{$this->allow();try{$this->service->request($r->string('target'),$r->int('server_id')?:null,$r->string('domain'),$r->string('email'));Response::redirect('/certificates');}catch(Throwable $e){http_response_code(422);exit(htmlspecialchars($e->getMessage(),ENT_QUOTES,'UTF-8'));}}
 private function allow():void{if(!Auth::can('certificates.manage')){http_response_code(403);exit('Sin permiso');}}
}
