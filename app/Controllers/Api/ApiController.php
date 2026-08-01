<?php
declare(strict_types=1);
namespace App\Controllers\Api;
use App\Core\{Request,Response};
abstract class ApiController
{
 protected function token(Request $r,string $audience,string $scope):array{$token=(array)$r->attribute('api_token');if(($token['audience']??'')!==$audience)Response::json(['ok'=>false,'error'=>['code'=>'forbidden','message'=>'Audiencia incorrecta.']],403);if(!in_array($scope,$token['scopes']??[],true))Response::json(['ok'=>false,'error'=>['code'=>'missing_scope','message'=>'Scope insuficiente.']],403);return $token;}
 protected function ok(array $data,int $status=200,array $meta=[]):never{Response::json(['ok'=>true,'data'=>$data,'meta'=>$meta],$status);}
}
