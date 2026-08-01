<?php
declare(strict_types=1);
namespace App\Controllers;
use App\Core\{Request,Response};use App\Services\{CompatibilityService,MagDeviceService};use Throwable;
final readonly class CompatibilityController
{
 public function __construct(private CompatibilityService $compat,private MagDeviceService $mag){}
 public function player(Request $r):void{$this->json(fn()=> $this->compat->player($this->compat->account($r->string('username'),$r->string('password')),$r->string('action')));}
 public function playlist(Request $r):void{try{$a=$this->compat->account($r->string('username'),$r->string('password'));header('Content-Type: audio/x-mpegurl; charset=utf-8');header('Content-Disposition: attachment; filename="playlist.m3u"');echo $this->compat->playlist($a,$r->string('password'));}catch(Throwable){http_response_code(401);echo '#EXTM3U';}}
 public function xmltv(Request $r):void{try{$this->compat->account($r->string('username'),$r->string('password'));header('Content-Type: application/xml; charset=utf-8');echo $this->compat->xmltv();}catch(Throwable){http_response_code(401);echo '<?xml version="1.0"?><tv></tv>';}}
 public function stream(Request $r):void{try{$resource=(string)$r->attribute('resource');$id=(int)strtok($resource,'.');$account=$this->compat->account((string)$r->attribute('username'),(string)$r->attribute('password'));header('Location: '.$this->compat->playback($account,$id,$r->server['REMOTE_ADDR']??null,$r->header('User-Agent')),true,302);}catch(Throwable $e){http_response_code(403);echo 'Acceso denegado';}}
 public function portal(Request $r):void{$this->magResponse($r);}
 public function stalker(Request $r):void{$this->magResponse($r);}
 private function magResponse(Request $r):void{$this->json(function()use($r){$action=$r->string('action');if($action==='handshake'){$mac=$r->string('mac')?:$this->cookieMac($r);return ['js'=>$this->mag->handshake($mac,$r->server['REMOTE_ADDR']??null,$r->header('User-Agent'))];}$token=$r->string('token')?:preg_replace('/^Bearer\s+/i','',(string)$r->header('Authorization'));$device=$this->mag->authenticate($token);return ['js'=>match($action){'get_profile'=>['id'=>(int)$device['id'],'name'=>$device['username'],'status'=>1,'exp_date'=>$device['expires_at']],'get_main_info'=>['phone'=>'','end_date'=>$device['expires_at'],'account_balance'=>'0'],'get_genres'=>$this->compat->categories('live'),'get_all_channels'=>$this->compat->magCatalog('live'),'get_categories'=>$this->compat->categories($r->string('type')==='vod'?'movie':'series'),'get_ordered_list'=>$this->compat->magCatalog($r->string('type')==='vod'?'movie':'series'),'create_link'=>$this->magLink($r,$device),default=>[] }];});}
 private function magLink(Request $r,array $device):array{$command=$r->string('cmd');if(!preg_match('/(?:\/|\s)(\d+)(?:\.[A-Za-z0-9]+)?(?:\?.*)?$/',$command,$match))throw new \RuntimeException('Contenido MAG inválido.');$path=$this->compat->playback(['id'=>(int)$device['account_id'],'max_connections'=>(int)$device['max_connections']],(int)$match[1],$r->server['REMOTE_ADDR']??null,$r->header('User-Agent'));return ['cmd'=>rtrim((string)$_ENV['APP_URL'],'/').$path];}
 private function cookieMac(Request $r):string{$cookie=(string)($r->server['HTTP_COOKIE']??'');if(preg_match('/(?:^|;\s*)mac=([^;]+)/i',$cookie,$m))return urldecode($m[1]);return '';}
 private function json(callable $fn):void{try{Response::json($fn());}catch(Throwable $e){Response::json(['user_info'=>['auth'=>0],'error'=>$e->getMessage()],401);}}
}
