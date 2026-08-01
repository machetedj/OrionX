<?php
declare(strict_types=1);

use App\Core\Environment;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class EnvironmentTest extends TestCase
{
    public function testAcceptsSecureProductionConfiguration(): void
    {
        Environment::validate($this->validEnvironment());
        $this->addToAssertionCount(1);
    }

    #[DataProvider('invalidProductionSettings')]
    public function testRejectsUnsafeProductionConfiguration(string $key, string $value): void
    {
        $environment = $this->validEnvironment();
        $environment[$key] = $value;
        $this->expectException(RuntimeException::class);
        Environment::validate($environment);
    }

    public static function invalidProductionSettings(): array
    {
        return [
            'placeholder key'=>['APP_KEY','change-me'],
            'debug enabled'=>['APP_DEBUG','true'],
            'insecure session'=>['SESSION_SECURE','false'],
            'missing database'=>['DB_DATABASE',''],
        ];
    }

    private function validEnvironment(): array
    {
        return [
            'APP_ENV'=>'production',
            'APP_DEBUG'=>'false',
            'APP_KEY'=>str_repeat('a',64),
            'CREDENTIALS_KEY'=>base64_encode(str_repeat('k',32)),
            'MEDIA_SIGNING_KEY'=>base64_encode(str_repeat('m',32)),
            'DB_HOST'=>'127.0.0.1',
            'DB_DATABASE'=>'panel',
            'DB_USERNAME'=>'panel_app',
            'SESSION_SECURE'=>'true',
        ];
    }
}
