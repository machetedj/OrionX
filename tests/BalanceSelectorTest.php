<?php
declare(strict_types=1);
use App\Domain\Servers\BalanceSelector;use PHPUnit\Framework\TestCase;
final class BalanceSelectorTest extends TestCase
{
 private function server(int $id,array $override=[]):array{return $override+['id'=>$id,'status'=>'online','last_heartbeat_at'=>date('Y-m-d H:i:s'),'max_capacity'=>100,'active_sessions'=>10,'weight'=>100,'priority'=>100,'region'=>'us-east','agent_status'=>'ok','nginx_status'=>'ok'];}
 public function testExcludesMaintenanceAndExpiredHeartbeat():void{$selected=(new BalanceSelector())->select([$this->server(1,['status'=>'maintenance']),$this->server(2,['last_heartbeat_at'=>'2000-01-01 00:00:00']),$this->server(3)]);$this->assertSame(3,$selected['id']);}
 public function testUsesPreferredHealthyServer():void{$selected=(new BalanceSelector())->select([$this->server(1),$this->server(2)],null,2);$this->assertSame(2,$selected['id']);}
 public function testPrefersRegionAndAvailableCapacity():void{$selected=(new BalanceSelector())->select([$this->server(1,['region'=>'eu','active_sessions'=>1]),$this->server(2,['region'=>'us-east','active_sessions'=>20])],'us-east');$this->assertSame(2,$selected['id']);}
}
