<?php
declare(strict_types=1);
namespace App\Controllers;
use App\Core\Request;use App\Services\MediaAccessService;use Throwable;
final readonly class MediaController
{
 public function __construct(private MediaAccessService $access){}
 public function serve(Request $request):void{try{$internal=$this->access->authorize((string)$request->attribute('token'),$request->server['REMOTE_ADDR']??null,$request->header('User-Agent'));header('X-Accel-Redirect: '.$internal);header('Cache-Control: private, no-store');http_response_code(200);}catch(Throwable){http_response_code(403);header('Cache-Control: no-store');echo 'Acceso denegado';}}
}
