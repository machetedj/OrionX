<?php
declare(strict_types=1);
namespace App\Repositories;
use PDO;
final readonly class UserRepository
{
    public function __construct(private PDO $db){}
    public function byLogin(string $login): ?array { $s=$this->db->prepare('SELECT * FROM users WHERE (LOWER(email)=LOWER(:email_login) OR name=:name_login) AND deleted_at IS NULL ORDER BY (LOWER(email)=LOWER(:order_login)) DESC,id DESC LIMIT 1'); $s->execute(['email_login'=>$login,'name_login'=>$login,'order_login'=>$login]); return $s->fetch()?:null; }
    public function byEmail(string $email): ?array { return $this->byLogin($email); }
    public function byId(int $id):?array{$s=$this->db->prepare('SELECT * FROM users WHERE id=? AND deleted_at IS NULL');$s->execute([$id]);return $s->fetch()?:null;}
    public function upgradePassword(int $id,string $password):void{$s=$this->db->prepare('UPDATE users SET password_hash=? WHERE id=?');$s->execute([password_hash($password,PASSWORD_ARGON2ID),$id]);}
    public function all(): array { return $this->db->query("SELECT u.id,u.name,u.email,u.status,u.created_at,GROUP_CONCAT(r.name ORDER BY r.name SEPARATOR ', ') group_names FROM users u LEFT JOIN user_roles ur ON ur.user_id=u.id LEFT JOIN roles r ON r.id=ur.role_id WHERE u.deleted_at IS NULL GROUP BY u.id ORDER BY u.id DESC LIMIT 200")->fetchAll(); }
    public function groups():array{return $this->db->query('SELECT id,name,is_admin,is_reseller FROM roles ORDER BY system_locked DESC,name')->fetchAll();}
    public function permissions(int $id): array { $s=$this->db->prepare('SELECT DISTINCT p.slug FROM permissions p JOIN role_permissions rp ON rp.permission_id=p.id JOIN user_roles ur ON ur.role_id=rp.role_id WHERE ur.user_id=:id'); $s->execute(['id'=>$id]); return array_column($s->fetchAll(),'slug'); }
    public function roles(int $id): array { $s=$this->db->prepare('SELECT r.slug FROM roles r JOIN user_roles ur ON ur.role_id=r.id WHERE ur.user_id=:id');$s->execute(['id'=>$id]);return array_column($s->fetchAll(),'slug'); }
    public function isReseller(int $id):bool{$s=$this->db->prepare('SELECT MAX(r.is_reseller) FROM roles r JOIN user_roles ur ON ur.role_id=r.id WHERE ur.user_id=?');$s->execute([$id]);return (int)$s->fetchColumn()===1;}
    public function isAdmin(int $id):bool{$s=$this->db->prepare('SELECT MAX(r.is_admin) FROM roles r JOIN user_roles ur ON ur.role_id=r.id WHERE ur.user_id=?');$s->execute([$id]);return (int)$s->fetchColumn()===1;}
    public function create(string $name,string $email,string $password,int $groupId): void { $check=$this->db->prepare('SELECT 1 FROM roles WHERE id=?');$check->execute([$groupId]);if(!$check->fetchColumn())throw new \RuntimeException('Grupo inválido.');$this->db->beginTransaction();try{$s=$this->db->prepare('INSERT INTO users(name,email,password_hash,status) VALUES(:name,:email,:hash,"active")');$s->execute(['name'=>$name,'email'=>$email,'hash'=>password_hash($password,PASSWORD_ARGON2ID)]);$this->db->prepare('INSERT INTO user_roles(user_id,role_id) VALUES(?,?)')->execute([(int)$this->db->lastInsertId(),$groupId]);$this->db->commit();}catch(\Throwable $e){if($this->db->inTransaction())$this->db->rollBack();throw $e;} }
    public function status(int $id,string $status):void{$s=$this->db->prepare('UPDATE users SET status=? WHERE id=? AND deleted_at IS NULL');$s->execute([$status,$id]);}
}
