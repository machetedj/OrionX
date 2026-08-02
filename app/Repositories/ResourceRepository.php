<?php
declare(strict_types=1);
namespace App\Repositories;
use PDO; use RuntimeException;
final readonly class ResourceRepository
{
    private const MAP=['categories'=>['name','type','active'],'servers'=>['name','public_ip','private_ip','region','status','max_capacity','weight','priority'],'resellers'=>['user_id','credit_balance','active']];
    public function __construct(private PDO $db){}
    public function all(string $resource): array { $this->guard($resource); return $this->db->query("SELECT * FROM $resource ORDER BY id DESC LIMIT 200")->fetchAll(); }
    public function serverOverview():array{return $this->db->query("SELECT s.*,COALESCE(m.cpu,0) cpu,COALESCE(m.ram,0) ram,COALESCE(m.disk,0) disk,COALESCE(m.network_rx_bps,0) network_rx_bps,COALESCE(m.network_tx_bps,0) network_tx_bps,COALESCE(m.active_sessions,0) active_sessions,COALESCE(m.active_streams,0) active_streams,COALESCE(m.connected_users,0) connected_users,EXISTS(SELECT 1 FROM server_ssh_credentials ssh WHERE ssh.server_id=s.id) ssh_configured,(SELECT cr.domain FROM certificate_requests cr WHERE cr.server_id=s.id AND cr.status='issued' ORDER BY cr.id DESC LIMIT 1) tls_domain FROM servers s LEFT JOIN server_metrics m ON m.id=(SELECT MAX(mx.id) FROM server_metrics mx WHERE mx.server_id=s.id) ORDER BY s.priority,s.id")->fetchAll();}
    public function insert(string $resource,array $data): void { $cols=self::MAP[$resource]??throw new RuntimeException('Recurso inválido'); $data=array_intersect_key($data,array_flip($cols)); $names=array_keys($data); $sql="INSERT INTO $resource(".implode(',',$names).') VALUES(:'.implode(',:',$names).')'; $this->db->prepare($sql)->execute($data); }
    private function guard(string $r): void { if(!isset(self::MAP[$r])) throw new RuntimeException('Recurso inválido'); }
}
