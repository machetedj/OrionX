<?php
declare(strict_types=1);
namespace App\Services;
use PDO;use RuntimeException;
final readonly class MediaAccessService
{
 public function __construct(private PDO $db,private MediaTokenService $tokens){}
 public function authorize(string $token,?string $ip,?string $userAgent):string
 {
  $p=$this->tokens->verify($token,$ip,$userAgent);$s=$this->db->prepare("SELECT f.id file_id,f.relative_path,f.status,l.id library_id,l.mount_path,a.id account_id,a.status account_status,a.expires_at,s.id session_id FROM content_files f JOIN storage_libraries l ON l.id=f.library_id JOIN active_sessions s ON s.id=? AND s.account_id=? AND s.disconnected_at IS NULL JOIN end_user_accounts a ON a.id=s.account_id WHERE f.public_id=? AND f.status='available' AND l.active=1 AND a.deleted_at IS NULL LIMIT 1");$s->execute([$p['sid'],$p['sub'],$p['rid']]);$row=$s->fetch();if(!$row||$row['account_status']!=='active'||($row['expires_at']!==null&&strtotime($row['expires_at'])<=time()))throw new RuntimeException('Acceso multimedia no autorizado.');
  $physical=realpath(rtrim($row['mount_path'],'/\\').DIRECTORY_SEPARATOR.$row['relative_path']);$root=realpath($row['mount_path']);if($physical===false||$root===false||!is_file($physical))throw new RuntimeException('Archivo no disponible.');$rootNormalized=rtrim(str_replace('\\','/',$root),'/').'/';$fileNormalized=str_replace('\\','/',$physical);$inside=DIRECTORY_SEPARATOR==='\\'?str_starts_with(strtolower($fileNormalized),strtolower($rootNormalized)):str_starts_with($fileNormalized,$rootNormalized);if(!$inside)throw new RuntimeException('Ruta multimedia inválida.');
  $this->db->prepare("INSERT INTO media_access_logs(token_id,account_id,content_file_id,session_id,ip,user_agent,result) VALUES(?,?,?,?,?,?,'allowed')")->execute([$p['jti'],$p['sub'],$row['file_id'],$p['sid'],$ip,substr((string)$userAgent,0,255)]);$segments=array_map('rawurlencode',explode('/',(string)$row['relative_path']));return '/__media/library-'.$row['library_id'].'/'.implode('/',$segments);
 }
}
