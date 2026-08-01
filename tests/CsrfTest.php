<?php
declare(strict_types=1);
use App\Core\{Csrf,Request}; use PHPUnit\Framework\TestCase;
final class CsrfTest extends TestCase { protected function setUp():void{$_SESSION=[];} public function testValidToken():void{$c=new Csrf();$t=$c->token();$this->assertTrue($c->verify(new Request('POST','/',['_csrf'=>$t],[])));} public function testRejectsInvalidToken():void{$c=new Csrf();$c->token();$this->assertFalse($c->verify(new Request('POST','/',['_csrf'=>'bad'],[])));} }
