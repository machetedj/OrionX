<?php
declare(strict_types=1);
namespace App\Repositories;
use PDO;
final readonly class WafRepository
{
 public function __construct(private PDO $db){}
 public function data():array{return ['settings'=>$this->db->query('SELECT * FROM waf_settings WHERE id=1')->fetch(),'rules'=>$this->db->query('SELECT * FROM waf_custom_rules ORDER BY active DESC,rule_id')->fetchAll(),'exclusions'=>$this->db->query('SELECT * FROM waf_exclusions ORDER BY active DESC,id DESC')->fetchAll(),'servers'=>$this->db->query("SELECT id,name,status,region FROM servers ORDER BY name")->fetchAll(),'deployments'=>$this->db->query("SELECT d.*,COALESCE(s.name,'Servidor principal') server_name FROM waf_deployments d LEFT JOIN servers s ON s.id=d.server_id ORDER BY d.id DESC LIMIT 30")->fetchAll(),'events'=>$this->db->query('SELECT * FROM waf_events ORDER BY occurred_at DESC LIMIT 100')->fetchAll(),'summary'=>$this->db->query("SELECT COUNT(*) events_24h,COUNT(DISTINCT ip) ips_24h,SUM(action IN ('blocked','deny')) blocked_24h FROM waf_events WHERE occurred_at>=DATE_SUB(NOW(),INTERVAL 24 HOUR)")->fetch()];}
}
