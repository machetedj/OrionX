<?php
declare(strict_types=1);
namespace App\Core;

final class Request
{
    private array $attributes=[];
    public function __construct(public string $method,public string $path,public array $input,public array $server,public string $rawBody=''){}
    public static function capture():self
    {
        $raw=(string)file_get_contents('php://input');$input=array_merge($_GET,$_POST);$contentType=strtolower((string)($_SERVER['CONTENT_TYPE']??''));
        if(str_contains($contentType,'application/json')&&$raw!==''){$json=json_decode($raw,true);if(is_array($json))$input=array_merge($input,$json);}
        return new self(strtoupper($_SERVER['REQUEST_METHOD']??'GET'),parse_url($_SERVER['REQUEST_URI']??'/',PHP_URL_PATH)?:'/',$input,$_SERVER,$raw);
    }
    public function string(string $key):string{return trim((string)($this->input[$key]??''));}
    public function int(string $key):int{return filter_var($this->input[$key]??0,FILTER_VALIDATE_INT)?:0;}
    public function header(string $name):?string{$key='HTTP_'.strtoupper(str_replace('-','_',$name));$value=$this->server[$key]??($name==='Content-Type'?($this->server['CONTENT_TYPE']??null):null);return $value===null?null:trim((string)$value);}
    public function setAttribute(string $key,mixed $value):void{$this->attributes[$key]=$value;}
    public function attribute(string $key,mixed $default=null):mixed{return $this->attributes[$key]??$default;}
}
