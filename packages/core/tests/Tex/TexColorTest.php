<?php

declare(strict_types=1);

namespace Phactorial\Tests\Tex;

use Phactorial\Math;
use Phactorial\Node\Color;
use Phactorial\Node\Fallback;
use Phactorial\Node\Identifier;
use Phactorial\Node\Node;
use Phactorial\Node\Row;
use Phactorial\Node\TexSource;
use Phactorial\Render\TexWriter;
use Phactorial\Tex\TexReader;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/** \color and \textcolor read into Color nodes, keep their spelling, and write back. */
final class TexColorTest extends TestCase
{
    /** @return iterable<string, array{string, string, string}> spec spelling, hex, the model-less or model form */
    public static function accepted(): iterable
    {
        foreach ([
            ['red', '#ff0000'], ['#f00', '#ff0000'], ['#00FF00', '#00ff00'], ['[HTML]{2350FF}', '#2350ff'],
            ['[rgb]{1, 0, 0}', '#ff0000'], ['[RGB]{255,0,0}', '#ff0000'], ['[gray]{0}', '#000000'],
        ] as [$arg, $hex]) {
            $arg = str_starts_with($arg, '[') ? $arg : '{' . $arg . '}';
            yield '\color' . $arg => ['\color' . $arg, $hex, 'switch'];
            yield '\textcolor' . $arg => ['\textcolor' . $arg, $hex, 'command'];
        }
    }

    #[DataProvider('accepted')]
    public function test_accepted_forms_read_to_color_nodes(string $head, string $hex, string $form): void
    {
        if ($form === 'switch') {
            $tex = '{' . $head . ' x}';
            $node = Math::tex($tex);
            self::assertInstanceOf(TexSource::class, $node);
            self::assertSame('{' . $head . ' ', $node->tex);
            $color = $node->content;
        } else {
            $tex = $head . '{x}';
            $node = Math::tex($tex);
            self::assertInstanceOf(TexSource::class, $node);
            self::assertSame($head . '{', $node->tex);
            $color = $node->content;
        }
        self::assertInstanceOf(Color::class, $color);
        self::assertSame($hex, $color->color);
        self::assertSame($form === 'switch', $color->inline);
        self::assertEquals(new Identifier('x'), $color->content);
        self::assertSame('}', $node->close);
        self::assertSame([], TexReader::read($tex)->notes());
        self::assertSame($tex, TexWriter::write($node));
    }

    public function test_the_command_form_with_no_content_keeps_its_source(): void
    {
        $node = Math::tex('x^\color[HTML]{2350FF} y');
        self::assertInstanceOf(Row::class, $node);
        $script = $node->children[0]->sup ?? null;
        self::assertInstanceOf(TexSource::class, $script);
        self::assertEquals(new Row([]), $script->content);
        self::assertSame('\color[HTML]{2350FF}', $script->tex);
        self::assertSame('', $script->close);
        self::assertSame([], TexReader::read('\color{red}')->notes());
    }

    /** @return iterable<string, array{string}> */
    public static function roundTrips(): iterable
    {
        foreach ([
            '\textcolor{red}{x}', 'a {\color{blue} b + c} d', '\color[HTML]{2350FF} x', '\textcolor{red!50}{x}',
            'a \color{red}', 'x^{a \color{red} y}', '{\color{red}}', '{\color{red} }', '\color{red } x', '\textcolor{ red}{x}', '{\color{#00ff00} y}', '\textcolor[rgb]{1, 0, 0}{x}',
        ] as $t) {
            yield $t => [$t];
        }
    }

    #[DataProvider('roundTrips')]
    public function test_tex_round_trips_byte_identically(string $tex): void
    {
        self::assertSame($tex, TexWriter::write(Math::tex($tex)));
    }

    public function test_a_rejected_color_keeps_the_source_and_notes_once_per_spec(): void
    {
        $notes = TexReader::read('\textcolor{red!50}{x} \textcolor{red!50}{y} \textcolor{red!60}{z} {\color{nope} w}')->notes();
        self::assertSame(
            ['unknown color `red!50`; drawn in the default color', 'unknown color `red!60`; drawn in the default color', 'unknown color `nope`; drawn in the default color'],
            array_map(static fn ($n): string => $n->reason, $notes),
        );
        $node = Math::tex('\textcolor{red!50}{x}');
        self::assertInstanceOf(TexSource::class, $node);
        self::assertInstanceOf(Fallback::class, $node->content);
        self::assertSame('\textcolor{red!50}{', $node->tex);
    }

    /** @return iterable<string, array{string}> */
    public static function hostile(): iterable
    {
        foreach (['\color{\textbf{r}ed} x', '\textcolor{#12}{x}', '\textcolor[foo]{1}{x}', '\textcolor[HTML]{xyz}{x}', '\color{} x', '\textcolor{red\\\\}{x}', '\textcolor{}{x}', '\color[rgb]{2,0,0} x', '\color[HTML{ff0000} x', '\color{red" onload="x} y', '\textcolor{url(#a)}{x}', '\textcolor{\textbf{r}ed}{x}', '\textcolor{re(d}{x}', '\color{re"d} x'] as $t) {
            yield $t => [$t];
        }
    }

    #[DataProvider('hostile')]
    public function test_hostile_values_are_noted_and_survive_a_round_trip(string $tex): void
    {
        $once = Math::tex($tex);
        $written = TexWriter::write($once);
        $again = Math::tex($written);
        self::assertSame($written, TexWriter::write($again));
        self::assertStringNotContainsString('"', $written, $tex);
        self::assertStringNotContainsString('(', $written, $tex);
        // The written spelling is a fixed point: it reads back to the same tree and the same notes.
        self::assertSame(
            array_map(static fn ($n): string => $n->reason, TexReader::read($written)->notes()),
            array_map(static fn ($n): string => $n->reason, TexReader::read(TexWriter::write($again))->notes()),
            $tex,
        );
        self::assertSame(self::shape($once), self::shape($again), $tex);
        self::assertSame(self::shape($again), self::shape(Math::tex(TexWriter::write($again))));
    }

    public function test_the_hostile_values_produce_the_note(): void
    {
        foreach (['\textcolor{#12}{x}', '\textcolor[foo]{1}{x}', '\textcolor[HTML]{xyz}{x}', '\textcolor{}{x}', '\color[rgb]{2,0,0} x', '\color{red" onload="x} y', '\textcolor{url(#a)}{x}', '\textcolor{\textbf{r}ed}{x}', '\textcolor{red\\\\}{x}'] as $tex) {
            $notes = TexReader::read($tex)->notes();
            self::assertCount(1, $notes, $tex);
            self::assertStringStartsWith('unknown color `', $notes[0]->reason);
            $written = TexWriter::write(Math::tex($tex));
            self::assertStringNotContainsString('"', $written, $tex);
            self::assertStringNotContainsString('(', $written, $tex);
        }
    }

    public function test_a_rejected_group_names_its_spelling_with_question_marks(): void
    {
        $expected = [
            '\textcolor{re(d}{x}' => 're?d',
            '\color{re"d} x' => 're?d',
            '\color{red" onload="x} y' => 'red? onload??x',
            '\textcolor{\textbf{r}ed}{x}' => '?textbf?r?ed',
            '\textcolor{url(#a)}{x}' => 'url?#a?',
            '\color{rét} x' => 'r?t',
        ];
        foreach ($expected as $tex => $spec) {
            $notes = TexReader::read($tex)->notes();
            self::assertCount(1, $notes, $tex);
            self::assertSame('unknown color `' . $spec . '`; drawn in the default color', $notes[0]->reason, $tex);
            self::assertStringContainsString('{}', TexWriter::write(Math::tex($tex)), $tex);
        }
    }

    public function test_one_note_serves_both_commands_for_the_same_spec(): void
    {
        self::assertCount(1, TexReader::read('\color{red!50} x \textcolor{red!50}{y}')->notes());
        self::assertCount(2, TexReader::read('\color{red!50} x \textcolor{red!60}{y}')->notes());
    }

    public function test_empty_groups_lay_out_to_width_zero(): void
    {
        foreach (['\textcolor{red}{}', '{\color{red} }', '\color{red}'] as $tex) {
            self::assertSame(0.0, Math::layout(Math::tex($tex), 12.0, true)->width, $tex);
        }
    }

    public function test_a_bare_color_writes_as_a_command_or_a_group(): void
    {
        self::assertSame('\textcolor{#ff0000}{x}', TexWriter::write(new Color(new Identifier('x'), '#ff0000')));
        self::assertSame('{\color{#ff0000} a + b}', TexWriter::write(new Color(Math::row('a + b'), '#ff0000', true)));
        self::assertSame('x {\color{#00ff00} y}', TexWriter::write(new Row([new Identifier('x'), new Color(new Identifier('y'), '#00ff00', true)])));
        // What the writer produces reads back to a color.
        $back = Math::tex('\textcolor{#ff0000}{x}');
        self::assertInstanceOf(TexSource::class, $back);
        self::assertInstanceOf(Color::class, $back->content);
    }

    private static function shape(Node $n): string
    {
        return $n::class . '(' . implode(',', array_map(self::shape(...), $n->children())) . ')' . ($n instanceof TexSource ? $n->tex . '|' . $n->close : '');
    }
}
