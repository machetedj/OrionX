<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class NoBlockingOverlayTest extends TestCase
{
    public function testViewsDoNotContainFullScreenBlockingOverlays(): void
    {
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(dirname(__DIR__) . '/resources/views'));
        foreach ($iterator as $file) {
            if (!$file->isFile() || $file->getExtension() !== 'php') continue;
            $contents = file_get_contents($file->getPathname());
            self::assertStringNotContainsString('fixed inset-0', (string) $contents, $file->getPathname());
        }
    }
}
