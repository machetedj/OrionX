<?php
declare(strict_types=1);
namespace App\Middleware;
use App\Core\{Request,Response};use App\Security\Auth;
final class ResellerMiddleware { public function handle(Request $request): void { if(!Auth::id()||Auth::portal()!=='reseller')Response::redirect('/reseller/login'); } }
