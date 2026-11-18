<?php

declare(strict_types=1);

namespace Phactorial\Recipes\Tests;

use PHPUnit\Framework\TestCase;

/**
 * The docs import recipe files from a fixed line (`@[code{N-} php](…)`). Lines above N are hidden, so they may hold
 * only the opening tag, blank lines and the one import the page states earlier; a line added there would vanish
 * from the page or shift it.
 */
final class DocImportsTest extends TestCase
{
    private const HIDDEN = ['<?php', '', 'use Phactorial\MathRender;'];

    public function test_imports_hide_only_the_header(): void
    {
        $imports = 0;
        foreach (glob(dirname(__DIR__, 2) . '/src/*.md') ?: [] as $page) {
            preg_match_all('/@\[code(?:\{(\d+)-\})? \w+\]\(\.\.\/recipes\/([^)]+)\)/', (string) file_get_contents($page), $m, PREG_SET_ORDER);
            foreach ($m as [, $start, $path]) {
                $imports++;
                $file = dirname(__DIR__) . '/' . $path;
                self::assertFileExists($file, basename($page));
                $lines = file($file, FILE_IGNORE_NEW_LINES) ?: [];
                $from = $start === '' ? 1 : (int) $start;

                foreach (array_slice($lines, 0, $from - 1) as $i => $hidden) {
                    self::assertContains($hidden, self::HIDDEN, "$path line " . ($i + 1) . ' is hidden from ' . basename($page));
                }
                self::assertNotSame('', trim($lines[$from - 1] ?? ''), "$path: the import from line $from starts on a blank line");
            }
        }

        self::assertSame(11, $imports);
    }
}
