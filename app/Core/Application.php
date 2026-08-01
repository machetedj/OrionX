<?php
declare(strict_types=1);
namespace App\Core;

use Dotenv\Dotenv;
use Monolog\Handler\RotatingFileHandler;
use Monolog\Logger;
use PDO;
use Predis\Client as RedisClient;

final class Application
{
    private Container $container;
    public readonly Router $router;

    private function __construct(public readonly string $basePath)
    {
        Dotenv::createImmutable($basePath)->safeLoad();
        Environment::validate($_ENV);
        $this->configureSession();
        $this->container = new Container();
        $this->router = new Router($this->container);
        $this->registerServices();
    }

    public static function boot(string $basePath): self { return new self($basePath); }
    public function get(string $id): mixed { return $this->container->get($id); }

    private function registerServices(): void
    {
        $base = $this->basePath;
        $this->container->set(PDO::class, static function (): PDO {
            $dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', $_ENV['DB_HOST'], $_ENV['DB_PORT'] ?? '3306', $_ENV['DB_DATABASE']);
            return new PDO($dsn, $_ENV['DB_USERNAME'], $_ENV['DB_PASSWORD'], [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC, PDO::ATTR_EMULATE_PREPARES=>false]);
        });
        $this->container->set(Logger::class, static function () use ($base): Logger {
            $logger = new Logger('panel');
            $logger->pushHandler(new RotatingFileHandler($base.'/storage/logs/app.log', 14, Logger::WARNING));
            return $logger;
        });
        $this->container->set(RedisClient::class, static fn(): RedisClient => new RedisClient(['scheme'=>'tcp','host'=>$_ENV['REDIS_HOST']??'127.0.0.1','port'=>(int)($_ENV['REDIS_PORT']??6379),'password'=>($_ENV['REDIS_PASSWORD']??'')?:null,'database'=>(int)($_ENV['REDIS_DATABASE']??0),'timeout'=>2.0]));
        $this->container->set(View::class, fn() => new View($base.'/resources/views'));
        $this->container->set(Csrf::class, fn() => new Csrf());
    }

    private function configureSession(): void
    {
        session_name('licensed_panel');
        session_set_cookie_params(['lifetime'=>0,'path'=>'/','secure'=>filter_var($_ENV['SESSION_SECURE'] ?? true,FILTER_VALIDATE_BOOL),'httponly'=>true,'samesite'=>'Strict']);
        if (session_status() !== PHP_SESSION_ACTIVE) session_start();
    }

    public function run(): void
    {
        try { $request=Request::capture();$ip=(string)($request->server['REMOTE_ADDR']??'');if($this->get(\App\Services\IpBlockService::class)->blocked($ip)){http_response_code(403);header('Retry-After: 3600');echo 'Acceso bloqueado';return;}$this->router->dispatch($request); }
        catch (\Throwable $e) {
            $this->get(Logger::class)->error($e->getMessage(), ['exception'=>get_class($e)]);
            http_response_code(500);
            echo filter_var($_ENV['APP_DEBUG'] ?? false,FILTER_VALIDATE_BOOL) ? htmlspecialchars($e->getMessage()) : 'Error interno';
        }
    }
}
