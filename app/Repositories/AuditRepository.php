<?php
declare(strict_types=1);
namespace App\Repositories;

use App\Security\Auth;
use PDO;

final readonly class AuditRepository
{
    public function __construct(private PDO $db){}
    public function record(string $action,string $entityType,?int $entityId,array $metadata=[]): void
    {
        $statement=$this->db->prepare('INSERT INTO audit_logs(user_id,action,entity_type,entity_id,ip,metadata) VALUES(:user,:action,:type,:entity,:ip,:metadata)');
        $statement->execute(['user'=>Auth::id(),'action'=>$action,'type'=>$entityType,'entity'=>$entityId,'ip'=>$_SERVER['REMOTE_ADDR']??null,'metadata'=>json_encode($metadata,JSON_THROW_ON_ERROR)]);
    }
}
