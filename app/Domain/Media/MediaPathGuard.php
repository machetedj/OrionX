<?php
declare(strict_types=1);
namespace App\Domain\Media;

use RuntimeException;

final class MediaPathGuard
{
    public function relative(string $mountPath,string $candidate): string
    {
        $root=realpath($mountPath);$file=realpath($candidate);
        if($root===false||$file===false||!is_file($file))throw new RuntimeException('Ruta multimedia inexistente.');
        $root=rtrim(str_replace('\\','/',$root),'/').'/';$normalized=str_replace('\\','/',$file);
        $inside=DIRECTORY_SEPARATOR==='\\'?str_starts_with(strtolower($normalized),strtolower($root)):str_starts_with($normalized,$root);
        if(!$inside)throw new RuntimeException('La ruta está fuera de la librería autorizada.');
        return substr($normalized,strlen($root));
    }
}
