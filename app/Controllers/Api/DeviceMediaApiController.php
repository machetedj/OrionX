<?php
declare(strict_types=1);
namespace App\Controllers\Api;
use App\Core\{Request,Response};use App\Services\MediaTokenService;use PDO;use Throwable;
final class DeviceMediaApiController extends ApiController
{
 public function __construct(private readonly PDO $db,private readonly MediaTokenService $tokens){}
 public function issue(Request $r):void{$access=$this->token($r,'device','media.play');$accountId=(int)$access['account_id'];$session=$r->string('session_id');$file=$r->string('file_id');try{$s=$this->db->prepare("SELECT 1 FROM content_files f JOIN content_items c ON c.id=f.content_item_id JOIN active_sessions sess ON sess.id=? AND sess.account_id=? AND sess.disconnected_at IS NULL JOIN end_user_accounts a ON a.id=sess.account_id AND a.status='active' AND (a.expires_at IS NULL OR a.expires_at>NOW()) WHERE f.public_id=? AND f.status='available' AND (EXISTS(SELECT 1 FROM account_content_items ac WHERE ac.account_id=a.id AND ac.content_item_id=c.id) OR EXISTS(SELECT 1 FROM account_categories cat WHERE cat.account_id=a.id AND cat.category_id=c.category_id) OR EXISTS(SELECT 1 FROM account_packages ap JOIN package_content_items pc ON pc.package_id=ap.package_id WHERE ap.account_id=a.id AND pc.content_item_id=c.id))");$s->execute([$session,$accountId,$file]);if(!$s->fetchColumn())throw new \RuntimeException('Recurso no autorizado.');$token=$this->tokens->issue($accountId,$session,$file,filter_var($r->input['bind_ip']??false,FILTER_VALIDATE_BOOL)?($r->server['REMOTE_ADDR']??null):null,$r->header('User-Agent'));$this->ok(['url'=>'/media/'.$token,'expires_in'=>max(30,min(600,(int)($_ENV['MEDIA_TOKEN_TTL']??120)))]);}catch(Throwable $e){Response::json(['ok'=>false,'error'=>['code'=>'media_denied','message'=>$e->getMessage()]],403);}}
}
