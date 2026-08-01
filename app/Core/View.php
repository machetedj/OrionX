<?php
declare(strict_types=1);
namespace App\Core;

final readonly class View
{
    public function __construct(private string $path) {}
    public function render(string $template,array $data=[]): void { extract($data,EXTR_SKIP); require $this->path.'/layout/header.php'; require $this->path.'/'.$template.'.php'; require $this->path.'/layout/footer.php'; }
}
