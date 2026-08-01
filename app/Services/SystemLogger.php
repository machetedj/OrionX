<?php
declare(strict_types=1);
namespace App\Services;
use App\Security\Auth;use Monolog\Logger;use PDO;
final readonly class SystemLogger
{
 private const CATEGORIES=['authentication','administration','reseller','api','balancer','stream','ffmpeg','import','tmdb','database','security','job','error'];private const LEVELS=['debug','info','notice','warning','error','critical'];
 public function __construct(private PDO $db,private Logger $fileLogger){}
 public function write(string $category,string $level,string $message,array $context=[],?string $correlationId=null):void{if(!in_array($category,self::CATEGORIES,true))$category='error';if(!in_array($level,self::LEVELS,true))$level='info';$context=$this->redact($context);$message=$this->sanitize($message);$this->db->prepare('INSERT INTO system_logs(category,level,message,context,correlation_id,actor_user_id,ip) VALUES(?,?,?,?,?,?,?)')->execute([$category,$level,substr($message,0,1000),json_encode($context,JSON_THROW_ON_ERROR),$correlationId,Auth::id(),$_SERVER['REMOTE_ADDR']??null]);$this->fileLogger->log($level,'['.$category.'] '.$message,$context);}
 private function redact(array $data):array{foreach($data as $key=>$value){if(preg_match('/password|secret|token|cookie|authorization|api.?key/i',(string)$key)){$data[$key]='[REDACTED]';continue;}$data[$key]=is_array($value)?$this->redact($value):(is_string($value)?$this->sanitize($value):$value);}return $data;}
 private function sanitize(string $value):string{$value=preg_replace('/([?&](?:token|key|signature|auth)=[^&\s]+)/i','[REDACTED]',$value)??$value;return preg_replace('#https?://[^\s]+#i','[SENSITIVE_URL]',$value)??$value;}
}
