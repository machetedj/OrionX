<?php
declare(strict_types=1);
namespace App\Domain\Streams;

final class FailoverSelector
{
    public function select(array $sources): ?array
    {
        $eligible=array_values(array_filter($sources,static fn(array $s): bool=>(bool)($s['authorized']??false)&&(bool)($s['active']??false)&&in_array($s['status']??'unknown',['healthy','unknown'],true)));
        usort($eligible,static fn(array $a,array $b): int=>[(int)($a['priority']??100),(int)($a['id']??0)]<=>[(int)($b['priority']??100),(int)($b['id']??0)]);
        return $eligible[0]??null;
    }
}
