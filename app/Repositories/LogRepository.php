<?php
declare(strict_types=1);
namespace App\Repositories;
use PDO;
final readonly class LogRepository
{
 public function __construct(private PDO $db){}
 public function search(array $filters,int $limit=200):array{$where=['1=1'];$params=[];foreach(['category','level'] as $key)if(!empty($filters[$key])){$where[]="$key=?";$params[]=$filters[$key];}if(!empty($filters['query'])){$where[]='message LIKE ?';$params[]='%'.str_replace(['%','_'],['\\%','\\_'],$filters['query']).'%';}$limit=max(1,min(1000,$limit));$s=$this->db->prepare('SELECT id,category,level,message,context,correlation_id,actor_user_id,ip,created_at FROM system_logs WHERE '.implode(' AND ',$where).' ORDER BY id DESC LIMIT '.$limit);$s->execute($params);return $s->fetchAll();}
}
