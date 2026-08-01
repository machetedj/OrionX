<?php
declare(strict_types=1);
namespace App\Core;

use ReflectionClass;

final class Container
{
    private array $bindings=[]; private array $instances=[];
    public function set(string $id, callable $factory): void { $this->bindings[$id]=$factory; }
    public function get(string $id): mixed
    {
        if (isset($this->instances[$id])) return $this->instances[$id];
        if (isset($this->bindings[$id])) return $this->instances[$id]=($this->bindings[$id])();
        $r=new ReflectionClass($id); $ctor=$r->getConstructor();
        $args=$ctor ? array_map(fn($p)=>$this->get($p->getType()->getName()),$ctor->getParameters()) : [];
        return $this->instances[$id]=$r->newInstanceArgs($args);
    }
}
