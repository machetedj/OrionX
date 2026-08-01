<?php
declare(strict_types=1);

namespace App\Core;

use PDO;
use RuntimeException;
use Throwable;

final readonly class MigrationRunner
{
    private const LOCK_NAME = 'licensed_media_panel_migrations';

    public function __construct(private PDO $db, private string $directory) {}

    /** @return list<string> */
    public function run(): array
    {
        $this->ensureRepository();
        if (!$this->lock()) {
            throw new RuntimeException('Otra ejecución de migraciones mantiene el bloqueo.');
        }

        $applied = [];
        try {
            foreach ($this->files() as $file) {
                $name = basename($file);
                $checksum = hash_file('sha256', $file);
                if ($checksum === false) throw new RuntimeException("No se pudo calcular el checksum de {$name}.");

                $recorded = $this->recordedChecksum($name);
                if ($recorded !== null) {
                    if ($recorded === '') {
                        $this->storeChecksum($name, $checksum);
                    } elseif (!hash_equals($recorded, $checksum)) {
                        throw new RuntimeException("La migración aplicada {$name} fue modificada.");
                    }
                    continue;
                }

                $sql = file_get_contents($file);
                if ($sql === false || trim($sql) === '') throw new RuntimeException("La migración {$name} está vacía o no se puede leer.");
                $this->db->exec($sql);
                $statement = $this->db->prepare('INSERT INTO schema_migrations(migration,checksum) VALUES(:migration,:checksum)');
                $statement->execute(['migration'=>$name,'checksum'=>$checksum]);
                $applied[] = $name;
            }
        } finally {
            $this->db->query("SELECT RELEASE_LOCK('".self::LOCK_NAME."')");
        }
        return $applied;
    }

    /** @return list<string> */
    private function files(): array
    {
        $files = glob(rtrim($this->directory, '/\\').'/*.sql') ?: [];
        sort($files, SORT_NATURAL);
        return array_values($files);
    }

    private function ensureRepository(): void
    {
        $this->db->exec('CREATE TABLE IF NOT EXISTS schema_migrations (migration VARCHAR(190) PRIMARY KEY, checksum CHAR(64) NULL, applied_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB');
        $column = $this->db->query("SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='schema_migrations' AND COLUMN_NAME='checksum'")->fetchColumn();
        if (!$column) $this->db->exec('ALTER TABLE schema_migrations ADD COLUMN checksum CHAR(64) NULL AFTER migration');
    }

    private function lock(): bool
    {
        $statement = $this->db->prepare('SELECT GET_LOCK(:name,10)');
        $statement->execute(['name'=>self::LOCK_NAME]);
        return (int)$statement->fetchColumn() === 1;
    }

    private function recordedChecksum(string $name): ?string
    {
        $statement = $this->db->prepare('SELECT checksum FROM schema_migrations WHERE migration=:migration');
        $statement->execute(['migration'=>$name]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);
        return $row === false ? null : (string)($row['checksum'] ?? '');
    }

    private function storeChecksum(string $name, string $checksum): void
    {
        $statement = $this->db->prepare('UPDATE schema_migrations SET checksum=:checksum WHERE migration=:migration');
        $statement->execute(['migration'=>$name,'checksum'=>$checksum]);
    }
}
