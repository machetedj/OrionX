<?php
declare(strict_types=1);
namespace App\Security;

use RuntimeException;

final class DeviceCredentialCipher
{
    private const VERSION="\x01";
    private const KEY_BYTES=32;
    private const NONCE_BYTES=12;
    private const TAG_BYTES=16;

    public function encrypt(string $plaintext): string
    {
        if($plaintext==='') throw new RuntimeException('El secreto no puede estar vacío.');
        $nonce=random_bytes(self::NONCE_BYTES);$tag='';
        $cipher=openssl_encrypt($plaintext,'aes-256-gcm',$this->key(),OPENSSL_RAW_DATA,$nonce,$tag,'device-credential-v1',self::TAG_BYTES);
        if($cipher===false||strlen($tag)!==self::TAG_BYTES)throw new RuntimeException('No fue posible cifrar la credencial.');
        return base64_encode(self::VERSION.$nonce.$tag.$cipher);
    }

    public function decrypt(string $encoded): string
    {
        $payload=base64_decode($encoded,true);$header=1+self::NONCE_BYTES+self::TAG_BYTES;
        if($payload===false||strlen($payload)<=$header||$payload[0]!==self::VERSION)throw new RuntimeException('Credencial cifrada inválida.');
        $nonce=substr($payload,1,self::NONCE_BYTES);$tag=substr($payload,1+self::NONCE_BYTES,self::TAG_BYTES);$cipher=substr($payload,$header);
        $plaintext=openssl_decrypt($cipher,'aes-256-gcm',$this->key(),OPENSSL_RAW_DATA,$nonce,$tag,'device-credential-v1');
        if($plaintext===false)throw new RuntimeException('No fue posible descifrar la credencial.');
        return $plaintext;
    }

    private function key(): string
    {
        $key=base64_decode((string)($_ENV['CREDENTIALS_KEY']??''),true);
        if($key===false||strlen($key)!==self::KEY_BYTES)throw new RuntimeException('CREDENTIALS_KEY inválida.');
        return $key;
    }
}
