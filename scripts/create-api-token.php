<?php
declare(strict_types=1);
use App\Core\Application;use App\Services\ApiTokenService;
require dirname(__DIR__).'/vendor/autoload.php';$audience=$argv[1]??'';$owner=(int)($argv[2]??0);$scopes=array_filter(explode(',',$argv[3]??''));$days=(int)($argv[4]??30);if(!in_array($audience,['admin','reseller','device'],true)||$owner<1||!$scopes)exit("Uso: php scripts/create-api-token.php admin|reseller|device OWNER_ID scope1,scope2 [dias]\n");$app=Application::boot(dirname(__DIR__));$token=$app->get(ApiTokenService::class)->issue($audience,$scopes,$days,$audience==='admin'?$owner:null,$audience==='reseller'?$owner:null,$audience==='device'?$owner:null);echo "Token (se muestra una sola vez): {$token}\n";
