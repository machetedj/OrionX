<?php
declare(strict_types=1);
namespace App\Controllers;

use App\Core\{Csrf,Request,Response,View};
use App\Repositories\AccountRepository;
use App\Security\Auth;
use App\Services\AccountService;
use Throwable;

final readonly class AccountController
{
    public function __construct(private View $view,private AccountRepository $accounts,private AccountService $service,private Csrf $csrf){}
    public function index(Request $request): void { $this->allow('accounts.view');$this->view->render('accounts/index',['title'=>'Cuentas finales','accounts'=>$this->accounts->all(),'packages'=>$this->accounts->packages(),'csrf'=>$this->csrf]); }
    public function store(Request $request): void { $this->allow('accounts.create');$this->attempt(fn()=> $this->service->create($request->input)); }
    public function status(Request $request): void { $this->allow('accounts.update');$this->attempt(fn()=> $this->service->setStatus($request->int('id'),$request->string('status'))); }
    public function renew(Request $request): void { $this->allow('accounts.update');$this->attempt(fn()=> $this->service->renew($request->int('id'),$request->int('days'))); }
    public function disconnect(Request $request): void { $this->allow('sessions.disconnect');$this->attempt(fn()=> $this->service->disconnect($request->int('id'))); }
    public function delete(Request $request): void { $this->allow('accounts.delete');$this->attempt(fn()=> $this->service->delete($request->int('id'))); }
    public function reveal(Request $request): void { $this->allow('accounts.secrets.view');try{$id=$request->int('id');$account=$this->accounts->find($id)??throw new \RuntimeException('Cuenta no encontrada.');$password=$this->service->reveal($id);$base=rtrim((string)$_ENV['APP_URL'],'/');Response::json(['credential'=>$password,'username'=>$account['username'],'server'=>$base,'m3u'=>$base.'/get.php?username='.rawurlencode($account['username']).'&password='.rawurlencode($password).'&type=m3u_plus&output=ts','epg'=>$base.'/xmltv.php?username='.rawurlencode($account['username']).'&password='.rawurlencode($password)]);}catch(Throwable $e){Response::json(['error'=>$e->getMessage()],422);} }
    private function attempt(callable $action): never { try{$action();Response::redirect('/accounts');}catch(Throwable $e){http_response_code(422);exit(htmlspecialchars($e->getMessage(),ENT_QUOTES,'UTF-8'));} }
    private function allow(string $permission): void { if(!Auth::can($permission)){http_response_code(403);exit('Sin permiso');} }
}
