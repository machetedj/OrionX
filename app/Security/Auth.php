<?php
declare(strict_types=1);
namespace App\Security;
use App\Repositories\UserRepository;

final readonly class Auth
{
    public function __construct(private UserRepository $users,private LegacyPassword $passwords) {}
    public function attempt(string $email,string $password,string $portal='admin'): bool
    {
        $user=$this->users->byLogin($email);
        if (!$user || $user['status']!=='active' || !$this->passwords->verify($password,$user['password_hash'])) return false;
        if($this->passwords->needsUpgrade($user['password_hash']))$this->users->upgradePassword((int)$user['id'],$password);
        $roles=$this->users->roles((int)$user['id']);$reseller=(bool)array_intersect($roles,['reseller','subreseller']);
        if(($portal==='reseller')!==$reseller)return false;
        session_regenerate_id(true); $_SESSION['user_id']=(int)$user['id']; $_SESSION['permissions']=$this->users->permissions((int)$user['id']);$_SESSION['roles']=$roles;$_SESSION['portal']=$portal; return true;
    }
    public static function id(): ?int { return isset($_SESSION['user_id'])?(int)$_SESSION['user_id']:null; }
    public static function can(string $permission): bool { return in_array($permission,$_SESSION['permissions']??[],true); }
    public static function portal(): ?string { return $_SESSION['portal']??null; }
    public static function logout(): void { $_SESSION=[]; if (ini_get('session.use_cookies')) { $p=session_get_cookie_params(); setcookie(session_name(),'',time()-42000,$p['path'],$p['domain'],$p['secure'],$p['httponly']); } session_destroy(); }
}
