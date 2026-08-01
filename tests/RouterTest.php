<?php
declare(strict_types=1);
use App\Core\{Container,Request,Router};use PHPUnit\Framework\TestCase;
final class RouterTest extends TestCase
{
 public function testDispatchesDynamicRouteAndExposesParameter():void{$router=new Router(new Container());$router->get('/media/{token}',[RouterTestHandler::class,'show']);ob_start();$router->dispatch(new Request('GET','/media/abc-123',[],[]));$this->assertSame('abc-123',ob_get_clean());}
}
final class RouterTestHandler{public function show(Request $request):void{echo $request->attribute('token');}}
