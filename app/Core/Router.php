<?php
declare(strict_types=1);
namespace App\Core;

final class Router
{
    private array $routes=[];
    private array $dynamic=[];
    public function __construct(private Container $container) {}
    public function get(string $path,array $handler,array $middleware=[]): void { $this->add('GET',$path,$handler,$middleware); }
    public function post(string $path,array $handler,array $middleware=[]): void { $this->add('POST',$path,$handler,$middleware); }
    private function add(string $method,string $path,array $handler,array $middleware): void { if(str_contains($path,'{')){$parts=explode('/',trim($path,'/'));$regex='';foreach($parts as $part)$regex.='/'.(preg_match('/^\{([A-Za-z_][A-Za-z0-9_]*)\}$/',$part,$m)?'(?P<'.$m[1].'>[^/]+)':preg_quote($part,'#'));$this->dynamic[$method][]=[$path,'#^'.($regex?:'/').'$#D',$handler,$middleware];return;}$this->routes[$method][$path]=[$handler,$middleware]; }
    public function dispatch(Request $request): void
    {
        [$handler,$middleware]=$this->routes[$request->method][$request->path]??[null,[]];
        if(!$handler)foreach($this->dynamic[$request->method]??[] as [$path,$regex,$candidate,$candidateMiddleware]){if(!preg_match($regex,$request->path,$matches))continue;foreach($matches as $key=>$value)if(is_string($key))$request->setAttribute($key,$value);$handler=$candidate;$middleware=$candidateMiddleware;break;}
        if (!$handler) { http_response_code(404); echo 'No encontrado'; return; }
        foreach ($middleware as $class) $this->container->get($class)->handle($request);
        [$class,$method]=$handler; $this->container->get($class)->$method($request);
    }
}
