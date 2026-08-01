<?php
declare(strict_types=1);
namespace App\Controllers;
use App\Core\{Csrf,Request,Response,View}; use App\Repositories\UserRepository; use App\Security\Auth;
final readonly class UserController
{
 public function __construct(private View $view,private UserRepository $repo,private Csrf $csrf){}
 public function index(Request $r):void{$this->deny();$this->view->render('users/index',['title'=>'Usuarios','users'=>$this->repo->all(),'csrf'=>$this->csrf]);}
 public function store(Request $r):void{$this->deny(); if(!filter_var($r->string('email'),FILTER_VALIDATE_EMAIL)||strlen($r->string('password'))<12){http_response_code(422);exit('Datos inválidos');}$this->repo->create($r->string('name'),$r->string('email'),$r->string('password'));Response::redirect('/users');}
 private function deny():void{if(!Auth::can('users.manage')){http_response_code(403);exit('Sin permiso');}}
}
