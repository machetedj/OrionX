<?php
declare(strict_types=1);

use App\Core\Environment;
use Dotenv\Dotenv;
use Predis\Client as RedisClient;

require dirname(__DIR__).'/vendor/autoload.php';
$basePath=dirname(__DIR__);
Dotenv::createImmutable($basePath)->load();

$failures=[];
$check=static function(bool $condition,string $ok,string $error) use (&$failures): void {
    if($condition){ echo "[OK] {$ok}\n"; return; }
    echo "[ERROR] {$error}\n";
    $failures[]=$error;
};

try { Environment::validate($_ENV); $check(true,'Configuración de entorno válida',''); }
catch(Throwable $e){ $check(false,'',$e->getMessage()); }

$check(PHP_VERSION_ID>=80300,'PHP 8.3 o superior','Se requiere PHP 8.3 o superior.');
foreach(['pdo','pdo_mysql','json','openssl','curl'] as $extension) $check(extension_loaded($extension),"Extensión {$extension} disponible","Falta la extensión PHP {$extension}.");
$ffmpeg=(string)($_ENV['FFMPEG_PATH']??'/usr/local/bin/ffmpeg');
$ffprobe=(string)($_ENV['FFPROBE_PATH']??'/usr/local/bin/ffprobe');
$check(is_file($ffmpeg)&&is_executable($ffmpeg),'FFmpeg oficial disponible','FFmpeg no está instalado en la ruta configurada.');
$check(is_file($ffprobe)&&is_executable($ffprobe),'FFprobe oficial disponible','FFprobe no está instalado en la ruta configurada.');
foreach(['storage/logs','storage/cache','storage/sessions'] as $directory){
    $path=$basePath.'/'.$directory;
    $check(is_dir($path)&&is_writable($path),"{$directory} escribible","{$directory} no existe o no es escribible.");
}

try {
    $pdo=new PDO(sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',$_ENV['DB_HOST'],$_ENV['DB_PORT']??'3306',$_ENV['DB_DATABASE']),$_ENV['DB_USERNAME'],$_ENV['DB_PASSWORD']??'',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
    $check(true,'Conexión a MariaDB/MySQL','');
    $required=['schema_migrations','users','roles','permissions','audit_logs'];
    $statement=$pdo->query("SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE()");
    $existing=array_flip(array_map('strval',$statement->fetchAll(PDO::FETCH_COLUMN)));
    foreach($required as $table) $check(isset($existing[$table]),"Tabla {$table} disponible","Falta la tabla {$table}; ejecuta las migraciones.");
    if(isset($existing['schema_migrations'])){
        $missing=(int)$pdo->query("SELECT COUNT(*) FROM schema_migrations WHERE checksum IS NULL OR checksum='' ")->fetchColumn();
        $check($missing===0,'Checksums de migraciones registrados','Hay migraciones sin checksum; ejecuta scripts/migrate.php.');
    }
} catch(Throwable $e){ $check(false,'','No se pudo validar la base: '.$e->getMessage()); }

try {
    $redis=new RedisClient(['scheme'=>'tcp','host'=>$_ENV['REDIS_HOST']??'127.0.0.1','port'=>(int)($_ENV['REDIS_PORT']??6379),'timeout'=>2]);
    $check((string)$redis->ping()==='PONG','Conexión a Redis','Redis no respondió correctamente.');
} catch(Throwable $e){ $check(false,'','No se pudo validar Redis: '.$e->getMessage()); }

if($failures){ echo "\nVerificación fallida con ".count($failures)." problema(s).\n"; exit(1); }
echo "\nInstalación verificada correctamente.\n";
