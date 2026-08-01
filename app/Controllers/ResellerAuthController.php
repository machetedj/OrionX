<?php
declare(strict_types=1);
namespace App\Controllers;
use App\Core\{Csrf,Request,Response,View};use App\Security\Auth;
final readonly class ResellerAuthController
{
 public function __construct(private View $view,private Auth $auth,private Csrf $csrf){}
 public function form(Request $r):void{$this->view->render('reseller/login',['title'=>'Acceso de revendedores','csrf'=>$this->csrf]);}
 public function login(Request $r):void{if(!$this->csrf->verify($r)){http_response_code(419);return;}if($this->auth->attempt($r->string('email'),$r->string('password'),'reseller'))Response::redirect('/reseller');$this->view->render('reseller/login',['title'=>'Acceso de revendedores','csrf'=>$this->csrf,'error'=>'Credenciales inválidas']);}
 public function logout(Request $r):void{Auth::logout();Response::redirect('/reseller/login');}
}
