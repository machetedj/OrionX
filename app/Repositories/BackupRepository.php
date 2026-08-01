<?php
declare(strict_types=1);
namespace App\Repositories;use PDO;
final readonly class BackupRepository{public function __construct(private PDO $db){}public function destinations():array{return $this->db->query("SELECT d.id,d.name,d.type,d.retention_days,d.active,d.last_backup_at,(SELECT COUNT(*) FROM backup_runs r WHERE r.destination_id=d.id AND r.status='completed') backups_count FROM backup_destinations d ORDER BY d.id DESC")->fetchAll();}public function runs():array{return $this->db->query('SELECT r.*,d.name destination_name,d.type destination_type FROM backup_runs r JOIN backup_destinations d ON d.id=r.destination_id ORDER BY r.id DESC LIMIT 200')->fetchAll();}}
