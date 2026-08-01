<?php
declare(strict_types=1);
namespace App\Middleware;
use App\Core\Csrf; use App\Core\Request;
final readonly class CsrfMiddleware { public function __construct(private Csrf $csrf){} public function handle(Request $r): void { if (!$this->csrf->verify($r)) { http_response_code(419); exit('Sesión expirada. Recarga la página.'); } } }
