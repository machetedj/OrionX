<?php
declare(strict_types=1);
namespace App\Core;

final class Csrf
{
    public function token(): string { return $_SESSION['_csrf'] ??= bin2hex(random_bytes(32)); }
    public function field(): string { return '<input type="hidden" name="_csrf" value="'.htmlspecialchars($this->token(),ENT_QUOTES).'">'; }
    public function verify(Request $request): bool { return isset($_SESSION['_csrf']) && hash_equals($_SESSION['_csrf'],$request->string('_csrf')); }
}
