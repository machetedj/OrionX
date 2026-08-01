<?php
declare(strict_types=1);
use App\Core\Application;use App\Services\LibraryScanner;
require dirname(__DIR__).'/vendor/autoload.php';
$libraryId=(int)($argv[1]??0);$type=(string)($argv[2]??'');if($libraryId<1||!in_array($type,['movie','series'],true))exit("Uso: php scripts/scan-library.php LIBRARY_ID movie|series\n");
$app=Application::boot(dirname(__DIR__));$result=$app->get(LibraryScanner::class)->scan($libraryId,$type);echo json_encode($result,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE).PHP_EOL;
