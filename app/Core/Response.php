<?php
declare(strict_types=1);
namespace App\Core;

final class Response
{
    public static function redirect(string $url): never { header('Location: '.$url,true,303); exit; }
    public static function json(array $data,int $status=200): never { http_response_code($status); header('Content-Type: application/json'); header('Cache-Control: no-store'); echo json_encode($data,JSON_THROW_ON_ERROR); exit; }
}
