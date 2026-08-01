<?php
declare(strict_types=1);
use App\Security\LegacyPassword;use PHPUnit\Framework\TestCase;
final class LegacyPasswordTest extends TestCase
{
 public function testPlainPasswordKeepsSameLoginAfterImport():void{$s=new LegacyPassword();$hash=$s->forImport('same-secret');$this->assertStringStartsWith('$argon2id$',$hash);$this->assertTrue($s->verify('same-secret',$hash));}
 public function testLegacyMd5AndSha1AreAcceptedForUpgrade():void{$s=new LegacyPassword();$this->assertTrue($s->verify('secret',md5('secret')));$this->assertTrue($s->verify('secret',sha1('secret')));$this->assertTrue($s->needsUpgrade(md5('secret')));}
}
