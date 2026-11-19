<?php

declare(strict_types=1);

namespace Phactorial\Tests\Render;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Phactorial\Math;
use Phactorial\Mathml\MathmlReader;
use Phactorial\Node\Node;
use Phactorial\Render\TexWriter;

final class OperatorSpacingTexTest extends TestCase
{
    private const float TOLERANCE = 0.05;

    private static function width(Node $n, bool $display): float
    {
        return Math::layout($n, 18.0, $display)->width;
    }

    private static function back(string $tex): Node
    {
        return Math::tex($tex);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function inputs(): iterable
    {
        yield 'integral lspace' => ['<math><mi>x</mi><mo lspace="0">∫</mo><mi>x</mi></math>'];
        yield 'integral msubsup lspace' => ['<math><mi>x</mi><msubsup><mo lspace="0">∫</mo><mn>0</mn><mn>1</mn></msubsup><mi>x</mi></math>'];
        yield 'integral munderover lspace' => ['<math><mi>x</mi><munderover><mo lspace="0">∫</mo><mn>0</mn><mn>1</mn></munderover><mi>x</mi></math>'];
        yield 'sum munderover lspace' => ['<math><mi>x</mi><munderover><mo lspace="0">∑</mo><mn>0</mn><mn>1</mn></munderover><mi>x</mi></math>'];
        yield 'plus rspace' => ['<math><mi>a</mi><mo rspace="0">+</mo><mi>b</mi></math>'];
        yield 'plus lspace' => ['<math><mi>a</mi><mo lspace="0">+</mo><mi>b</mi></math>'];
        yield 'movablelimits x' => ['<math><munder><mo movablelimits="true">x</mo><mi>a</mi></munder></math>'];
        yield 'movablelimits plus' => ['<math><munder><mo movablelimits="true">+</mo><mi>a</mi></munder></math>'];
        yield 'explicit form is not written' => ['<math><mi>a</mi><mo lspace="0" form="prefix">+</mo><mi>b</mi></math>'];
        yield 'underset base' => ['<math><mi>a</mi><munder><mo lspace="0">=</mo><mi>i</mi></munder><mi>b</mi></math>'];
        yield 'underset kern base' => ['<math><mi>a</mi><munder><mo lspace="0" rspace="2">+</mo><mi>i</mi></munder><mi>b</mi></math>'];
        yield 'nested underset base' => ['<math><mi>a</mi><munder><munder><mo lspace="0">=</mo><mi>i</mi></munder><mi>j</mi></munder><mi>b</mi></math>'];
        yield 'rel class limits movable' => ['<math><mi>a</mi><munder><mo lspace="thickmathspace" rspace="thickmathspace">∑</mo><mi>i</mi></munder><mi>b</mi></math>'];
        yield 'rel class limits fixed' => ['<math><mi>a</mi><munder><mo movablelimits="false" lspace="thickmathspace" rspace="thickmathspace">∑</mo><mi>i</mi></munder><mi>b</mi></math>'];
        yield 'movable plus thick' => ['<math><mi>a</mi><munder><mo movablelimits="true" lspace="thickmathspace" rspace="thickmathspace">+</mo><mi>i</mi></munder><mi>b</mi></math>'];
        yield 'movable equals medium' => ['<math><mi>a</mi><munder><mo movablelimits="true" lspace="mediummathspace" rspace="mediummathspace">=</mo><mi>i</mi></munder><mi>b</mi></math>'];
        yield 'movable equals zero' => ['<math><mi>a</mi><munder><mo movablelimits="true" lspace="0" rspace="0">=</mo><mi>i</mi></munder><mi>b</mi></math>'];
        yield 'letter base scripts' => ['<math><mi>a</mi><msub><mo>x</mo><mi>i</mi></msub><mi>b</mi></math>'];
        yield 'letter base stack' => ['<math><mi>a</mi><munder><mo lspace="0">x</mo><mi>i</mi></munder><mi>b</mi></math>'];
        yield 'letter in a row' => ['<math><mi>a</mi><mo lspace="thickmathspace">x</mo><mi>b</mi></math>'];
        yield 'unspaced letter in a row' => ['<math><mi>a</mi><mo>x</mo><mi>b</mi></math>'];
        yield 'colored letter in a row' => ['<math><mi>a</mi><mstyle mathcolor="red"><mo lspace="0">x</mo></mstyle><mi>b</mi></math>'];
        yield 'colored one-sided plus in a row' => ['<math><mi>a</mi><mstyle mathcolor="red"><mo lspace="0">+</mo></mstyle><mi>b</mi></math>'];
        yield 'large operator spaced one mu' => ['<math><mi>a</mi><munder><mo lspace="0.0555556em" rspace="0.0555556em">∑</mo><mi>i</mi></munder><mi>b</mi></math>'];
        yield 'sum rspace limits' => ['<math><mi>x</mi><munder><mo rspace="0">∑</mo><mi>a</mi></munder><mi>x</mi></math>'];
    }

    #[DataProvider('inputs')]
    public function testWidthSurvivesTexWriting(string $mathml): void
    {
        $node = MathmlReader::read($mathml)->node;
        $tex = TexWriter::write($node);
        $back = self::back($tex);
        foreach ([false, true] as $display) {
            self::assertEqualsWithDelta(self::width($node, $display), self::width($back, $display), self::TOLERANCE, $tex . ($display ? ' (display)' : ' (inline)'));
        }
    }

    public function testTexBornLettersWriteUnchanged(): void
    {
        foreach (['d x', '\Delta x', 'a \mathrel{x} b', 'a + b'] as $tex) {
            self::assertSame($tex, TexWriter::write(self::back($tex)));
        }
    }

    public function testClassSpellingsStayUnchanged(): void
    {
        self::assertSame('a \mathrel{:} b', TexWriter::write(self::back('a \mathrel{:} b')));
        self::assertSame('a \mathbin{=} b', TexWriter::write(self::back('a \mathbin{=} b')));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function classedLargeOperators(): iterable
    {
        yield 'rel beside' => ['a \mathrel{\sum}_a b'];
    }

    #[DataProvider('classedLargeOperators')]
    public function testClassWrappedLargeOperatorKeepsItsClass(string $tex): void
    {
        self::assertSame($tex, TexWriter::write(self::back($tex)));
    }

    /**
     * @return iterable<string, array{string, string, string}>
     */
    public static function controlAfterClass(): iterable
    {
        yield 'rel limits' => ['a \mathrel{\sum}\limits_a b', 'a \mkern2mu \sum\limits_a \mkern2mu b', '\limits'];
        yield 'rel limits no scripts' => ['a \mathrel{\sum}\limits b', 'a \mathrel{\sum} b', '\limits'];
        yield 'colored rel limits' => ['a \textcolor{red}{\mathrel{\sum}}\limits_a b', 'a \mkern2mu \mathop{\textcolor{red}{\sum}}\limits_a \mkern2mu b', '\limits'];
        yield 'negated rel limits' => ['a \not\mathrel{\sum}\limits_a b', 'a \underset{a}{\mathrel{\not\sum}} b', '\limits'];
        yield 'bin limits' => ['a \mathbin{\sum}\limits_a b', 'a \mkern1mu \sum\limits_a \mkern1mu b', '\limits'];
    }

    #[DataProvider('controlAfterClass')]
    public function testControlAfterAClassAtomIsNotedAndWrittenLegally(string $tex, string $written, string $element): void
    {
        $r = \Phactorial\Tex\TexReader::read($tex);
        $notes = array_map(static fn ($n): string => $n->element . ': ' . $n->reason, $r->notes());
        self::assertSame([$element . ': a limit control must follow an operator'], $notes);
        self::assertSame($written, TexWriter::write($r->node));
    }

    #[DataProvider('inputs')]
    public function testNoLimitsControlFollowsAClassGroup(string $mathml): void
    {
        $tex = TexWriter::write(MathmlReader::read($mathml)->node);
        self::assertDoesNotMatchRegularExpression('/\\\\math(?:rel|bin|punct|ord|open|close)\{[^{}]*\}\\\\(?:no|display)?limits/', $tex);
    }

    public function testNestedStacksKeepOneGroup(): void
    {
        $node = MathmlReader::read('<math><mi>a</mi><munder><munder><mo lspace="0">=</mo><mi>i</mi></munder><mi>j</mi></munder><mi>b</mi></math>')->node;
        self::assertSame('a \underset{j}{{\mkern-5mu \underset{i}{=}}} b', TexWriter::write($node));
    }

    public function testKernsStayOutsideAStackedBase(): void
    {
        $node = MathmlReader::read('<math><mi>a</mi><munder><mo lspace="0">=</mo><mi>i</mi></munder><mi>b</mi></math>')->node;
        $tex = TexWriter::write($node);
        self::assertStringContainsString('\\underset{i}{=}', $tex);
        self::assertStringNotContainsString('{\\mkern', $tex);
        self::assertStringContainsString('\\mkern', $tex);
    }

    public function testColoredLimitsKeepTheirControlAndWidth(): void
    {
        foreach (['\textcolor{red}{\sum}\limits_a^b', '\mathop{\textcolor{red}{\int}}_0^1'] as $tex) {
            $node = self::back($tex);
            $written = TexWriter::write($node);
            $back = self::back($written);
            foreach ([false, true] as $display) {
                self::assertEqualsWithDelta(self::width($node, $display), self::width($back, $display), self::TOLERANCE, $written);
            }
        }
    }

    public function testLimitsControlStillFollowsTheOperator(): void
    {
        $node = MathmlReader::read('<math><munderover><mo lspace="0">∑</mo><mn>0</mn><mn>1</mn></munderover></math>')->node;
        $tex = TexWriter::write($node);
        self::assertDoesNotMatchRegularExpression('/\\\\mkern[^\\\\]*\\\\(?:no|display)?limits/', $tex);
    }
}
