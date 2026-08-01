<?php
declare(strict_types=1);
namespace App\Repositories;use PDO;
final readonly class EpgRepository{public function __construct(private PDO $db){}public function sources():array{return $this->db->query("SELECT e.*,(SELECT COUNT(*) FROM epg_programmes p WHERE p.source_id=e.id) programmes,(SELECT COUNT(DISTINCT external_channel_id) FROM epg_programmes p WHERE p.source_id=e.id) channels FROM epg_sources e ORDER BY e.id DESC")->fetchAll();}}
