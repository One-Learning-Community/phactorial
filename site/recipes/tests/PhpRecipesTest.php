<?php

declare(strict_types=1);

namespace Phactorial\Recipes\Tests;

use Phactorial\MathRender;
use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use PHPUnit\Framework\TestCase;

/** The recipes page, run as written there, held to the output its comments promise. */
final class PhpRecipesTest extends TestCase
{
    public function test_web_page(): void
    {
        $output = Snippet::run('php/web-page.php')['output'];

        self::assertStringStartsWith('<p>The area of a circle is <svg', $output);
        self::assertSame(2, substr_count($output, '<svg'));
        self::assertStringNotContainsString('vertical-align', substr($output, strrpos($output, '<svg')));
    }

    public function test_accessible(): void
    {
        self::assertStringContainsString(
            'aria-label="x = (−b ± √(b² − 4ac))/(2a)"',
            Snippet::run('php/accessible.php')['output'],
        );
    }

    public function test_accessible_mathml(): void
    {
        $output = Snippet::run('php/accessible-mathml.php')['output'];

        self::assertStringContainsString('aria-hidden="true"', $output);
        self::assertStringContainsString('<span class="visually-hidden"><math', $output);
    }

    #[RequiresPhpExtension('imagick')]
    public function test_email(): void
    {
        self::assertStringContainsString('src="cid:formula-1"', Snippet::run('php/email.php')['output']);
    }

    public function test_word(): void
    {
        $vars = Snippet::run('php/word.php')['vars'];

        self::assertStringStartsWith('<m:oMath', (string) $vars['inline']);
        self::assertStringStartsNotWith('<m:oMathPara', (string) $vars['inline']);
        self::assertStringStartsWith('<m:oMathPara', (string) $vars['block']);
    }

    public function test_converting(): void
    {
        $vars = Snippet::run('php/converting.php')['vars'];

        self::assertSame('\frac{a}{b}', $vars['tex']);
        self::assertStringContainsString('<msqrt>', (string) $vars['mathml']);
    }

    public function test_untrusted(): void
    {
        self::assertInstanceOf(MathRender::class, Snippet::run('php/untrusted.php', ['userInput' => '\frac{'])['vars']['formula']);
    }
}
