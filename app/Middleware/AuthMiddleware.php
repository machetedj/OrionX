<?php
declare(strict_types=1);namespace App\Middleware;use App\Core\{Request,Response};use App\Repositories\UserRepository;use App\Security\Auth;
final readonly class AuthMiddleware{public function __construct(private UserRepository $users){}public function handle(Request $request):void{$id=Auth::id();$user=$id?$this->users->byId($id):null;if(!$user||$user['status']!=='active'||Auth::portal()!=='admin'){Auth::logout();Response::redirect('/login');}$_SESSION['permissions']=$this->users->permissions($id);$_SESSION['roles']=$this->users->roles($id);}}
