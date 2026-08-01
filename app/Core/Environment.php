<?php
declare(strict_types=1);

namespace App\Core;

use RuntimeException;

final class Environment
{
    private const REQUIRED = ['APP_ENV','APP_KEY','CREDENTIALS_KEY','MEDIA_SIGNING_KEY','DB_HOST','DB_DATABASE','DB_USERNAME'];

    public static function validate(array $env): void
    {
        foreach (self::REQUIRED as $key) {
            if (!isset($env[$key]) || trim((string)$env[$key]) === '') {
                throw new RuntimeException("Falta la variable de entorno {$key}.");
            }
        }

        $key = (string)$env['APP_KEY'];
        if (!preg_match('/^[a-f0-9]{64}$/i', $key)) {
            throw new RuntimeException('APP_KEY debe contener exactamente 64 caracteres hexadecimales.');
        }
        $credentialsKey=base64_decode((string)$env['CREDENTIALS_KEY'],true);
        if($credentialsKey===false || strlen($credentialsKey)!==32){
            throw new RuntimeException('CREDENTIALS_KEY debe ser una clave Base64 de 32 bytes.');
        }
        $mediaKey=base64_decode((string)$env['MEDIA_SIGNING_KEY'],true);
        if($mediaKey===false||strlen($mediaKey)<32)throw new RuntimeException('MEDIA_SIGNING_KEY debe ser una clave Base64 de al menos 32 bytes.');

        if (($env['APP_ENV'] ?? 'production') === 'production') {
            if (filter_var($env['APP_DEBUG'] ?? false, FILTER_VALIDATE_BOOL)) {
                throw new RuntimeException('APP_DEBUG debe estar desactivado en producción.');
            }
            if (!filter_var($env['SESSION_SECURE'] ?? true, FILTER_VALIDATE_BOOL)) {
                throw new RuntimeException('SESSION_SECURE debe estar activado en producción.');
            }
        }
    }
}
