<?php
declare(strict_types=1);
namespace App\Domain\Servers;
final class BalanceSelector
{
 public function select(array $servers,?string $region=null,?int $preferredId=null,int $heartbeatTtl=90):?array
 {
  $now=time();$eligible=array_values(array_filter($servers,static function(array $s)use($now,$heartbeatTtl):bool{$heartbeat=strtotime((string)($s['last_heartbeat_at']??''));$capacity=max(1,(int)($s['max_capacity']??0));$sessions=(int)($s['active_sessions']??0);return in_array($s['status']??'offline',['online','degraded'],true)&&$heartbeat!==false&&$heartbeat>=$now-$heartbeatTtl&&$sessions<$capacity&&($s['agent_status']??'ok')==='ok'&&($s['nginx_status']??'ok')==='ok';}));
  if($preferredId!==null)foreach($eligible as $server)if((int)$server['id']===$preferredId)return $server;
  usort($eligible,static function(array $a,array $b)use($region):int{return self::score($b,$region)<=>self::score($a,$region)?:((int)$a['id']<=>(int)$b['id']);});return $eligible[0]??null;
 }
 private static function score(array $s,?string $region):int{$capacity=max(1,(int)$s['max_capacity']);$free=max(0,$capacity-(int)($s['active_sessions']??0));$regionFactor=$region!==null&&strcasecmp((string)($s['region']??''),$region)===0?200:100;$health=($s['status']??'')==='online'?100:60;return (int)round(max(1,(int)($s['weight']??100))*$free/$capacity*$regionFactor*$health/max(1,(int)($s['priority']??100)));}
}
