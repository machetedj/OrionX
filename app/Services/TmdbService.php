<?php
declare(strict_types=1);
namespace App\Services;

use JsonException;
use Monolog\Logger;
use Predis\Client as RedisClient;
use RuntimeException;

final readonly class TmdbService
{
 private const API='https://api.themoviedb.org/3';
 public function __construct(private RedisClient $cache,private Logger $logger){}
 public function search(string $type,string $title,?int $year=null,?string $language=null,int $page=1):array
 {
  if(!in_array($type,['movie','tv'],true))throw new RuntimeException('Tipo TMDB inválido.');$title=trim($title);if($title===''||mb_strlen($title)>255)throw new RuntimeException('Título inválido.');
  $language=$this->language($language);$params=['query'=>$title,'language'=>$language,'page'=>max(1,min(500,$page)),'include_adult'=>'false'];
  if($year!==null){if($year<1888||$year>(int)date('Y')+5)throw new RuntimeException('Año inválido.');$params[$type==='movie'?'primary_release_year':'first_air_date_year']=$year;}
  return $this->get('/search/'.$type,$params);
 }
 public function details(string $type,int $tmdbId,?string $language=null):array
 {
  if(!in_array($type,['movie','tv'],true)||$tmdbId<1)throw new RuntimeException('Identificador TMDB inválido.');
  return $this->get('/'.$type.'/'.$tmdbId,['language'=>$this->language($language),'append_to_response'=>'credits,images,translations,videos']);
 }
 public function translations(string $type,int $tmdbId):array { return $this->get('/'.$type.'/'.$tmdbId.'/translations',[]); }
 public function downloadImage(string $remotePath,string $targetDirectory,string $size='original'):string
 {
  if(!preg_match('#^/[A-Za-z0-9._/-]+\.(?:jpg|jpeg|png|webp)$#i',$remotePath))throw new RuntimeException('Ruta de imagen TMDB inválida.');
  if(!preg_match('/^(?:original|w\d{2,4}|h\d{2,4})$/',$size))throw new RuntimeException('Tamaño de imagen inválido.');
  if(!is_dir($targetDirectory)&&!mkdir($targetDirectory,0750,true)&&!is_dir($targetDirectory))throw new RuntimeException('No se pudo crear el directorio de imágenes.');
  $name=hash('sha256',$size.$remotePath).'.'.strtolower(pathinfo($remotePath,PATHINFO_EXTENSION));$target=rtrim($targetDirectory,'/\\').DIRECTORY_SEPARATOR.$name;
  if(is_file($target))return $target;$temporary=$target.'.part-'.bin2hex(random_bytes(4));$handle=fopen($temporary,'wb');if($handle===false)throw new RuntimeException('No se pudo crear la imagen temporal.');
  $curl=curl_init('https://image.tmdb.org/t/p/'.$size.$remotePath);curl_setopt_array($curl,[CURLOPT_FILE=>$handle,CURLOPT_FOLLOWLOCATION=>false,CURLOPT_CONNECTTIMEOUT=>5,CURLOPT_TIMEOUT=>30,CURLOPT_FAILONERROR=>true,CURLOPT_PROTOCOLS=>CURLPROTO_HTTPS,CURLOPT_USERAGENT=>'LicensedMediaPanel/1.0']);$ok=curl_exec($curl);$type=(string)curl_getinfo($curl,CURLINFO_CONTENT_TYPE);$error=curl_error($curl);curl_close($curl);fclose($handle);
  if(!$ok||!str_starts_with($type,'image/')||filesize($temporary)>20*1024*1024){@unlink($temporary);throw new RuntimeException('Descarga de imagen rechazada: '.$error);}
  if(!rename($temporary,$target)){@unlink($temporary);throw new RuntimeException('No se pudo guardar la imagen.');}return $target;
 }
 private function get(string $path,array $params):array
 {
  $key='tmdb:v1:'.hash('sha256',$path.'?'.http_build_query($params));$cached=$this->cache->get($key);if(is_string($cached)){try{return json_decode($cached,true,512,JSON_THROW_ON_ERROR);}catch(JsonException){$this->cache->del([$key]);}}
  $result=$this->request($path,$params);$this->cache->setex($key,max(300,(int)($_ENV['TMDB_CACHE_TTL']??86400)),json_encode($result,JSON_THROW_ON_ERROR));return $result;
 }
 private function request(string $path,array $params):array
 {
  $token=trim((string)($_ENV['TMDB_BEARER_TOKEN']??''));if($token==='')throw new RuntimeException('TMDB_BEARER_TOKEN no está configurado.');$url=self::API.$path.($params?'?'.http_build_query($params):'');
  for($attempt=1;$attempt<=4;$attempt++){$this->rateLimit();$headers=[];$curl=curl_init($url);curl_setopt_array($curl,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_FOLLOWLOCATION=>false,CURLOPT_CONNECTTIMEOUT=>5,CURLOPT_TIMEOUT=>20,CURLOPT_PROTOCOLS=>CURLPROTO_HTTPS,CURLOPT_HTTPHEADER=>['Authorization: Bearer '.$token,'Accept: application/json'],CURLOPT_HEADERFUNCTION=>static function($c,string $line)use(&$headers):int{$parts=explode(':',$line,2);if(count($parts)===2)$headers[strtolower(trim($parts[0]))]=trim($parts[1]);return strlen($line);}]);$body=curl_exec($curl);$status=(int)curl_getinfo($curl,CURLINFO_RESPONSE_CODE);$error=curl_error($curl);curl_close($curl);
   if($body!==false&&$status>=200&&$status<300)return json_decode($body,true,512,JSON_THROW_ON_ERROR);
   $retry=$status===429||$status>=500;if(!$retry||$attempt===4){$this->logger->error('TMDB request failed',['path'=>$path,'status'=>$status,'attempt'=>$attempt,'error'=>$error]);throw new RuntimeException('TMDB no respondió correctamente (HTTP '.$status.').');}
   $delay=min(5,max(1,(int)($headers['retry-after']??$attempt)));usleep($delay*1000000);
  }throw new RuntimeException('TMDB no disponible.');
 }
 private function rateLimit():void{$limit=max(1,min(35,(int)($_ENV['TMDB_RATE_LIMIT']??30)));$key='tmdb:rate:'.time();$count=(int)$this->cache->incr($key);if($count===1)$this->cache->expire($key,2);if($count>$limit)usleep(1000000);}
 private function language(?string $language):string{$language=$language?:($_ENV['TMDB_DEFAULT_LANGUAGE']??'es-ES');if(!preg_match('/^[a-z]{2}-[A-Z]{2}$/',(string)$language))throw new RuntimeException('Idioma TMDB inválido.');return (string)$language;}
}
