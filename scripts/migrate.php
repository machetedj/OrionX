<?php
declare(strict_types=1);

use App\Core\Environment;
use App\Core\MigrationRunner;
use Dotenv\Dotenv;

require dirname(__DIR__).'/vendor/autoload.php';
Dotenv::createImmutable(dirname(__DIR__))->load();
Environment::validate($_ENV);

$pdo = new PDO(
    sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', $_ENV['DB_HOST'], $_ENV['DB_PORT'] ?? '3306', $_ENV['DB_DATABASE']),
    $_ENV['DB_USERNAME'],
    $_ENV['DB_PASSWORD'] ?? '',
    [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC, PDO::ATTR_EMULATE_PREPARES=>false]
);

$applied = (new MigrationRunner($pdo, dirname(__DIR__).'/database/migrations'))->run();
foreach ($applied as $migration) echo "Aplicada: {$migration}\n";
echo $applied === [] ? "Esquema actualizado; no había cambios.\n" : "Migraciones completadas.\n";
