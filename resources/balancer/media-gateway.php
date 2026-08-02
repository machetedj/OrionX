<?php
declare(strict_types=1);
$config=parse_ini_file('/opt/media-balancer/agent.env',false,INI_SCANNER_RAW)?:[];
$token=basename((string)(parse_url($_SERVER['REQUEST_URI']??'',PHP_URL_PATH)??''));
$decode=static function(string $value):string{$padding=(4-strlen($value)%4)%4;$decoded=base64_decode(strtr($value,'-_','+/').str_repeat('=',$padding),true);return $decoded===false?'':$decoded;};
$parts=explode('.',$token);if(count($parts)!==2){http_response_code(403);exit;}
[$body,$signature]=$parts;$key=base64_decode((string)($config['MEDIA_SIGNING_KEY']??''),true);$provided=$decode($signature);
if($key===false||strlen($key)<32||!hash_equals(hash_hmac('sha256',$body,$key,true),$provided)){http_response_code(403);exit;}
$payload=json_decode($decode($body),true);$id=(string)($payload['rid']??'');
if(!is_array($payload)||(int)($payload['exp']??0)<time()||!preg_match('/^[a-f0-9]{32}$/',$id)){http_response_code(403);exit;}
if(($payload['ip']??null)!==null&&!hash_equals((string)$payload['ip'],(string)($_SERVER['REMOTE_ADDR']??''))){http_response_code(403);exit;}
if(($payload['uah']??null)!==null&&!hash_equals((string)$payload['uah'],hash('sha256',(string)($_SERVER['HTTP_USER_AGENT']??'')))){http_response_code(403);exit;}
$link='/srv/orionx/media/'.$id;$real=realpath($link);if($real===false||!is_file($real)||!is_link($link)){http_response_code(404);exit;}
header('X-Accel-Redirect: /__vod/'.$id);header('Cache-Control: private, no-store');header('X-Content-Type-Options: nosniff');http_response_code(200);
