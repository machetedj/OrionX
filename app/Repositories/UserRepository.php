<?php
declare(strict_types=1);
namespace App\Repositories;
use PDO;
final readonly class UserRepository
{
    public function __construct(private PDO $db){}
    public function byLogin(string $login): ?array { $s=$this->db->prepare('SELECT * FROM users WHERE (LOWER(email)=LOWER(:login) OR name=:login) AND deleted_at IS NULL ORDER BY (LOWER(email)=LOWER(:login)) DESC,id DESC LIMIT 1'); $s->execute(['login'=>$login]); return $s->fetch()?:null; }
    public function byEmail(string $email): ?array { return $this->byLogin($email); }
    public function byId(int $id):?array{$s=$this->db->prepare('SELECT * FROM users WHERE id=? AND deleted_at IS NULL');$s->execute([$id]);return $s->fetch()?:null;}
    public function upgradePassword(int $id,string $password):void{$s=$this->db->prepare('UPDATE users SET password_hash=? WHERE id=?');$s->execute([password_hash($password,PASSWORD_ARGON2ID),$id]);}
    public function all(): array { return $this->db->query('SELECT id,name,email,status,created_at FROM users WHERE deleted_at IS NULL ORDER BY id DESC LIMIT 200')->fetchAll(); }
    public function permissions(int $id): array { $s=$this->db->prepare('SELECT DISTINCT p.slug FROM permissions p JOIN role_permissions rp ON rp.permission_id=p.id JOIN user_roles ur ON ur.role_id=rp.role_id WHERE ur.user_id=:id'); $s->execute(['id'=>$id]); return array_column($s->fetchAll(),'slug'); }
    public function roles(int $id): array { $s=$this->db->prepare('SELECT r.slug FROM roles r JOIN user_roles ur ON ur.role_id=r.id WHERE ur.user_id=:id');$s->execute(['id'=>$id]);return array_column($s->fetchAll(),'slug'); }
    public function create(string $name,string $email,string $password): void { $s=$this->db->prepare('INSERT INTO users(name,email,password_hash,status) VALUES(:name,:email,:hash,"active")'); $s->execute(['name'=>$name,'email'=>$email,'hash'=>password_hash($password,PASSWORD_ARGON2ID)]); }
    public function status(int $id,string $status):void{$s=$this->db->prepare('UPDATE users SET status=? WHERE id=? AND deleted_at IS NULL');$s->execute([$status,$id]);}
}
