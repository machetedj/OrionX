<?php
declare(strict_types=1);
namespace App\Middleware;
use App\Core\Request; use App\Core\Response; use App\Security\Auth;
final class AuthMiddleware { public function handle(Request $request): void { if (!Auth::id()||Auth::portal()!=='admin') Response::redirect('/login'); } }
