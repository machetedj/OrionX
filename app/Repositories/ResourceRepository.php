<?php
declare(strict_types=1);
namespace App\Repositories;
use PDO; use RuntimeException;
final readonly class ResourceRepository
{
    private const MAP=['categories'=>['name','type','active'],'servers'=>['name','public_ip','private_ip','region','status','max_capacity','weight','priority'],'resellers'=>['user_id','credit_balance','active']];
    public function __construct(private PDO $db){}
    public function all(string $resource): array { $this->guard($resource); return $this->db->query("SELECT * FROM $resource ORDER BY id DESC LIMIT 200")->fetchAll(); }
    public function insert(string $resource,array $data): void { $cols=self::MAP[$resource]??throw new RuntimeException('Recurso inválido'); $data=array_intersect_key($data,array_flip($cols)); $names=array_keys($data); $sql="INSERT INTO $resource(".implode(',',$names).') VALUES(:'.implode(',:',$names).')'; $this->db->prepare($sql)->execute($data); }
    private function guard(string $r): void { if(!isset(self::MAP[$r])) throw new RuntimeException('Recurso inválido'); }
}
