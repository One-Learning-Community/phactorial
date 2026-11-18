<?php

declare(strict_types=1);

namespace Phactorial\Recipes\Tests;

use PHPUnit\Framework\TestCase;

/** The harness itself: a snippet's variables are its own, whatever they are called. */
final class SnippetTest extends TestCase
{
    public function test_caller_vars_cannot_replace_the_included_file(): void
    {
        $vars = Snippet::run('php/converting.php', ['file' => 'kept', 'vars' => 'kept', 'output' => 'kept'])['vars'];

        self::assertSame('kept', $vars['file']);
        self::assertSame('kept', $vars['vars']);
        self::assertSame('kept', $vars['output']);
        self::assertSame('\frac{a}{b}', $vars['tex']);
    }
}
