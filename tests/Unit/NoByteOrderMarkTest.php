<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/**
 * A UTF-8 byte-order mark at the start of a PHP/Blade file is sent to the browser before the
 * response body, which silently corrupts JSON responses. Editors/shell tools sometimes add one.
 */
class NoByteOrderMarkTest extends TestCase
{
    public function test_no_php_or_blade_file_starts_with_a_bom(): void
    {
        $root = dirname(__DIR__, 2);
        $offenders = [];

        foreach (['app', 'bootstrap', 'config', 'database', 'routes', 'resources/views'] as $dir) {
            $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator("$root/$dir", RecursiveDirectoryIterator::SKIP_DOTS));
            foreach ($it as $file) {
                if (preg_match('/\.php$/', $file->getFilename()) && str_starts_with((string) file_get_contents($file->getPathname(), false, null, 0, 3), "\xEF\xBB\xBF")) {
                    $offenders[] = str_replace($root.DIRECTORY_SEPARATOR, '', $file->getPathname());
                }
            }
        }

        $this->assertSame([], $offenders, 'Remove the UTF-8 BOM from: '.implode(', ', $offenders));
    }
}
