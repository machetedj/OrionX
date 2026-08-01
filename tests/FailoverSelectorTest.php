<?php
declare(strict_types=1);
use App\Domain\Streams\FailoverSelector;use PHPUnit\Framework\TestCase;
final class FailoverSelectorTest extends TestCase
{
 public function testSelectsHighestPriorityAuthorizedHealthySource():void{$selected=(new FailoverSelector())->select([['id'=>1,'priority'=>1,'authorized'=>false,'active'=>true,'status'=>'healthy'],['id'=>2,'priority'=>20,'authorized'=>true,'active'=>true,'status'=>'healthy'],['id'=>3,'priority'=>10,'authorized'=>true,'active'=>true,'status'=>'failed']]);$this->assertSame(2,$selected['id']);}
 public function testReturnsNullWhenNoAuthorizedSourceExists():void{$this->assertNull((new FailoverSelector())->select([['id'=>1,'authorized'=>false,'active'=>true,'status'=>'healthy']]));}
}
