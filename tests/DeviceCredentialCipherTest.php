<?php
declare(strict_types=1);

use App\Security\DeviceCredentialCipher;
use PHPUnit\Framework\TestCase;

final class DeviceCredentialCipherTest extends TestCase
{
    protected function setUp(): void { $_ENV['CREDENTIALS_KEY']=base64_encode(str_repeat('x',32)); }
    public function testEncryptsAndDecryptsCredential(): void
    {
        $cipher=new DeviceCredentialCipher();$encrypted=$cipher->encrypt('device-secret-123');
        $this->assertNotSame('device-secret-123',$encrypted);
        $this->assertSame('device-secret-123',$cipher->decrypt($encrypted));
    }
    public function testUsesRandomNonce(): void
    {
        $cipher=new DeviceCredentialCipher();
        $this->assertNotSame($cipher->encrypt('same-secret'),$cipher->encrypt('same-secret'));
    }
    public function testRejectsTamperedCredential(): void
    {
        $cipher=new DeviceCredentialCipher();$payload=base64_decode($cipher->encrypt('device-secret-123'),true);$payload[strlen($payload)-1]=chr(ord($payload[strlen($payload)-1])^1);
        $this->expectException(RuntimeException::class);$cipher->decrypt(base64_encode($payload));
    }
}
