<?php

declare(strict_types=1);

namespace Phactorial\CommonMark\Tests;

use League\CommonMark\Environment\Environment;
use League\CommonMark\Extension\CommonMark\CommonMarkCoreExtension;
use League\CommonMark\MarkdownConverter;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Phactorial\CommonMark\MathExtension;

final class SyntaxTest extends TestCase
{
    private function convert(string $markdown, string $output = 'mathml'): string
    {
        $environment = new Environment(['math' => ['output' => $output]]);
        $environment->addExtension(new CommonMarkCoreExtension());
        $environment->addExtension(new MathExtension());

        return (new MarkdownConverter($environment))->convert($markdown)->getContent();
    }

    private static function occurrences(string $html, string $needle): int
    {
        return substr_count($html, $needle);
    }

    /** @return iterable<string, array{string, array<string, int>}> */
    public static function counts(): iterable
    {
        yield 'inline' => ['area $\pi r^2$ here', ['<span class="math math-inline">' => 1, '<span class="math math-display">' => 0, '<div class="math' => 0, '<math' => 1]];
        yield 'backtick inline' => ['Euler $`e^{i\pi}+1=0`$', ['<span class="math math-inline">' => 1, '<math' => 1]];
        yield 'display span in paragraph' => ['a $$\sum k$$ b', ['<span class="math math-display">' => 1, '<div' => 0, '<p>' => 1, '<math' => 1]];
        yield 'display block' => ["$$\nx\n$$", ['<div class="math math-display">' => 1, '<p>' => 0, '<math' => 1]];
        yield 'one-line display block' => ['$$x = 1$$', ['<div class="math math-display">' => 1, '<p>' => 0, '<math' => 1]];
        yield 'math fence' => ["```math\nx^2\n```", ['<div class="math math-display">' => 1, '<pre>' => 0, '<math' => 1]];
        yield 'other fence untouched' => ["```php\n\$a\$\n```", ['<pre><code class="language-php">$a$' => 1, '<math' => 0]];
        yield 'prices' => ['costs $5 and $10', ['<math' => 0, 'costs $5 and $10' => 1]];
        yield 'escaped dollar' => ['a lone \$ sign', ['<math' => 0, 'a lone $ sign' => 1]];
        yield 'code span' => ['`$x$`', ['<code>$x$</code>' => 1, '<math' => 0]];
        yield 'spaced' => ['$ spaced $', ['<math' => 0, '$ spaced $' => 1]];
        yield 'escaped dollar then code' => ['$10. A lone \$ and `$x$`', ['<math' => 0, '<code>$x$</code>' => 1]];
        yield 'blockquote' => ["> \$\$\n> x\n> \$\$", ['<blockquote>' => 1, '<div class="math math-display">' => 1, '<math' => 1, '<p>' => 0]];
        yield 'list item' => ["- item\n\n  \$\$\n  x\n  \$\$", ['<li>' => 1, '<div class="math math-display">' => 1, '<math' => 1]];
        yield 'multibyte' => ['é $x$ ü', ['<p>é <span class="math math-inline">' => 1, '</span> ü</p>' => 1, '<math' => 1]];
        yield 'strong and link' => ['**bold $v$** and [link $x^2$](https://example.com)', ['<strong>bold <span class="math math-inline">' => 1, '<a href="https://example.com">link <span class="math math-inline">' => 1, '<math' => 2]];
    }

    /** @param array<string, int> $expected */
    #[DataProvider('counts')]
    public function test_syntax(string $markdown, array $expected): void
    {
        $html = $this->convert($markdown);
        foreach ($expected as $needle => $count) {
            self::assertSame($count, self::occurrences($html, $needle), "$needle in: $html");
        }
    }

    public function test_crlf_matches_lf(): void
    {
        $lf = $this->convert("$$\nx\n$$\n");
        $crlf = $this->convert("$$\r\nx\r\n$$\r\n");

        self::assertSame(1, self::occurrences($crlf, '<div class="math math-display">'));
        self::assertSame($lf, $crlf);
    }

    public function test_crlf_inline_and_multiline_matches_lf(): void
    {
        $lf = $this->convert("a \$x\$ b\n\n\$\$\na\n+\nb\n\$\$\n\nend \$y\$");
        $crlf = $this->convert("a \$x\$ b\r\n\r\n\$\$\r\na\r\n+\r\nb\r\n\$\$\r\n\r\nend \$y\$");

        self::assertSame($lf, $crlf);
    }

    public function test_multibyte_inside_math_keeps_cursor_aligned(): void
    {
        $html = $this->convert('é $\text{é}$ ü $y$ ö');

        self::assertSame(2, self::occurrences($html, '<span class="math math-inline">'));
        self::assertStringContainsString('<p>é ', $html);
        self::assertStringContainsString('</span> ü ', $html);
        self::assertStringContainsString('</span> ö</p>', $html);
    }

    public function test_svg_output_is_the_default(): void
    {
        $environment = new Environment();
        $environment->addExtension(new CommonMarkCoreExtension());
        $environment->addExtension(new MathExtension());
        $html = (new MarkdownConverter($environment))->convert('a $x$')->getContent();

        self::assertStringContainsString('<svg', $html);
        self::assertStringContainsString('role="img"', $html);
        self::assertStringNotContainsString('<math', $html);
    }
}
