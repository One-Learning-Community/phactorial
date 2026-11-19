<?php

declare(strict_types=1);

namespace Phactorial\Tests\Node;

use Phactorial\Math;
use Phactorial\Node\Node;
use Phactorial\Render\MathmlWriter;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class FormsTest extends TestCase
{
    private static function mathml(string $tex): string
    {
        return MathmlWriter::write(Math::tex($tex));
    }

    /** The width a middle item takes, with its spacing: the whole row less the items either side. */
    private static function gap(Node $row, Node $left, Node $right): float
    {
        return Math::layout($row, 12.0, true)->width - Math::layout($left, 12.0, true)->width - Math::layout($right, 12.0, true)->width;
    }

    /** @return iterable<string, array{string, string}> */
    public static function infixAfterFactorial(): iterable
    {
        yield 'equals' => ['n! = b', '<mo form="postfix">!</mo><mo>=</mo>'];
        yield 'plus' => ['n! + b', '<mo form="postfix">!</mo><mo>+</mo>'];
        yield 'le' => ['n! \le b', '<mo form="postfix">!</mo><mo>≤</mo>'];
        yield 'sim' => ['n! \sim b', '<mo form="postfix">!</mo><mo>∼</mo>'];
        yield 'double sim' => ['x \sim \sim b', '<mo>∼</mo><mo>∼</mo>'];
        yield 'after a paren' => ['(a) \sim b', '<mo>∼</mo>'];
    }

    #[DataProvider('infixAfterFactorial')]
    public function test_relation_or_binary_after_close_is_infix(string $tex, string $expected): void
    {
        $out = self::mathml($tex);
        self::assertStringContainsString($expected, $out);
        self::assertStringNotContainsString('form="prefix"', $out);
    }

    public function test_repeated_factorial_stays_postfix(): void
    {
        $out = self::mathml('n!!');
        self::assertStringNotContainsString('form="prefix"', $out);
        self::assertStringContainsString('<mo form="postfix">!</mo><mo>!</mo>', $out);
        self::assertStringContainsString('<mo form="postfix">!</mo><mo form="postfix">!</mo><mo>+</mo>', self::mathml('n!! + b'));
    }

    /** @return iterable<string, array{string, int}> */
    public static function prefixKept(): iterable
    {
        yield 'a = -b' => ['a = -b', 1];
        yield 'a + + b' => ['a + + b', 1];
        yield 'a, -b' => ['a, -b', 1];
        yield 'to -infty' => ['x \to -\infty', 1];
        yield '-a' => ['-a', 0]; // first in its row: the writer leaves the form off
    }

    #[DataProvider('prefixKept')]
    public function test_prefix_operators_stay_prefix(string $tex, int $count): void
    {
        self::assertSame($count, substr_count(self::mathml($tex), 'form="prefix"'));
    }

    public function test_spacing_after_factorial_matches_plain_operand(): void
    {
        foreach (['\sim', '+', '='] as $op) {
            $plain = self::gap(Math::tex("a $op b"), Math::tex('a'), Math::tex('b'));
            $fact = self::gap(Math::tex("n! $op b"), Math::tex('n!'), Math::tex('b'));
            self::assertGreaterThan(5.0, $plain);
            self::assertEqualsWithDelta($plain, $fact, 1e-6, $op);
        }
    }

    public function test_spacing_after_a_relation_matches_plain_operand(): void
    {
        $plain = self::gap(Math::tex('x \sim b'), Math::tex('x'), Math::tex('b'));
        $double = self::gap(Math::tex('x \sim \sim b'), Math::tex('x'), Math::tex('b'));
        self::assertEqualsWithDelta(2 * $plain, $double, 1e-6);
    }

    public function test_mathml_input_resolves_the_same_way(): void
    {
        $node = Math::mathml('<math><mi>n</mi><mo>!</mo><mo>∼</mo><mi>b</mi></math>');
        $out = MathmlWriter::write($node);
        self::assertStringNotContainsString('form="prefix"', $out);
        $plain = self::gap(Math::mathml('<math><mi>a</mi><mo>∼</mo><mi>b</mi></math>'), Math::tex('a'), Math::tex('b'));
        $fact = self::gap($node, Math::tex('n!'), Math::tex('b'));
        self::assertEqualsWithDelta($plain, $fact, 1e-6);
    }
}
