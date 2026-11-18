<?php

declare(strict_types=1);

namespace Phactorial\CommonMark\Parser;

use League\CommonMark\Parser\Cursor;
use League\CommonMark\Parser\Inline\InlineParserInterface;
use League\CommonMark\Parser\Inline\InlineParserMatch;
use League\CommonMark\Parser\InlineParserContext;
use Phactorial\CommonMark\Node\MathInline;

/**
 * GitHub's math, as its renderer treats it (the rules are pinned by tests/fixtures/github):
 *
 * - $`…`$ may span lines and may follow any character.
 * - $…$ stays on one line. The opener is not followed by white space and not preceded by a letter, digit, underscore
 *   or dollar sign. The closer is not preceded by a backslash and not followed by a letter, digit or underscore, so
 *   "costs $5 and $10" and "$a$b$" stay text. Inner white space before the closer is fine.
 * - $$…$$ follows the same rules on one line and is inline math. When the whole paragraph starts and ends with $$,
 *   every $$…$$ in it is display math, may span lines, may touch white space and ignores the follower rule; a paragraph
 *   holding only one display formula becomes a block (see MathBlockListener).
 * - A backslash-escaped dollar never closes a formula, and a code span inside the formula, which GitHub reads first,
 *   ends the attempt. A closer may be followed by a non-ASCII letter, but an opener may not be preceded by one.
 *
 * An opener with no closer after it must not rescan the paragraph, or a paragraph of unclosed openers parses in
 * quadratic time. The last possible closer is found once per paragraph (once per line for inline $$) and compared
 * with each opener's position. A single-dollar formula cannot contain a dollar sign, so its scan ends at the next one.
 */
final class MathInlineParser implements InlineParserInterface
{
    /** What may not follow a closer: an ASCII letter, digit or underscore. GitHub lets "é" follow. */
    private const string WORD = '[A-Za-z0-9_]';
    /** What may not precede an opener: the same, or any non-ASCII byte, because GitHub rejects "é$x$". */
    private const string BEFORE = '[A-Za-z0-9_\x80-\xFF]';

    /** @var \WeakReference<Cursor>|null The cursor whose paragraph the cached facts below belong to. */
    private ?\WeakReference $cursorRef = null;
    private int $lastBacktickCloser = -1;
    private bool $displayMode = false;
    private int $displayBound = -1;
    private int $lineStart = 0;
    private int $lineEnd = -1;
    private int $lineBound = -1;
    /** @var array<int, true>|null Offsets of the backtick runs that open a code span; built when first needed. */
    private ?array $codeOpeners = null;

    public function getMatchDefinition(): InlineParserMatch
    {
        return InlineParserMatch::string('$');
    }

    public function parse(InlineParserContext $inlineContext): bool
    {
        $cursor = $inlineContext->getCursor();
        $text = $cursor->getLine();
        $pos = $cursor->getBytePosition();
        $this->prepare($cursor, $text);

        $found = $this->match($text, $pos);
        if ($found === null) {
            return false;
        }
        [$length, $tex, $display] = $found;
        $source = substr($text, $pos, $length);
        $cursor->advanceBy(mb_strlen($source, 'UTF-8'));
        $inlineContext->getContainer()->appendChild(new MathInline($tex, $display, $source));

        return true;
    }

    /** @return array{int, string, bool}|null The matched byte length, the TeX and whether it is display math. */
    private function match(string $text, int $pos): ?array
    {
        $next = $text[$pos + 1] ?? '';
        $prev = $pos > 0 ? $text[$pos - 1] : '';
        $afterWord = $prev !== '' && preg_match('/' . self::BEFORE . '/', $prev) === 1;

        if ($next === '`' && $pos + 2 <= $this->lastBacktickCloser
            && preg_match('/\G\$`(.+?)`\$/s', $text, $m, 0, $pos) === 1) {
            return [strlen($m[0]), $m[1], false];
        }

        if ($next === '$') {
            return $afterWord ? null : $this->matchDouble($text, $pos);
        }

        if ($afterWord || $prev === '$' || $next === '' || ctype_space($next)) {
            return null;
        }
        if (preg_match('/\G\$([^$\n]+)(?<!\\\\)\$(?!' . self::WORD . ')/', $text, $m, 0, $pos) !== 1
            || $this->hasCodeSpan($text, $pos + 1, strlen($m[1]))) {
            return null;
        }

        return [strlen($m[0]), $m[1], false];
    }

    /** @return array{int, string, bool}|null */
    private function matchDouble(string $text, int $pos): ?array
    {
        if ($this->displayMode) {
            $fits = $pos + 3 <= $this->displayBound;
            $pattern = '/\G\$\$(.+?)(?<!\\\\)\$\$/s';
        } else {
            $fits = $pos + 3 <= $this->boundOnLine($text, $pos) && ! ctype_space($text[$pos + 2] ?? ' ');
            $pattern = '/\G\$\$([^\n]+?)(?<!\\\\)\$\$(?!' . self::WORD . ')/';
        }
        if (! $fits || preg_match($pattern, $text, $m, 0, $pos) !== 1 || $this->hasCodeSpan($text, $pos + 2, strlen($m[1]))) {
            return null;
        }

        return [strlen($m[0]), $m[1], $this->displayMode];
    }

    private function prepare(Cursor $cursor, string $text): void
    {
        if ($this->cursorRef?->get() === $cursor) {
            return;
        }
        $this->cursorRef = \WeakReference::create($cursor);
        $position = strrpos($text, '`$');
        $this->lastBacktickCloser = $position === false ? -1 : $position;
        $this->codeOpeners = null;
        $this->lineEnd = -1;
        $this->displayMode = str_starts_with($text, '$$') && str_ends_with(rtrim($text), '$$');
        $this->displayBound = $this->displayMode ? $this->lastCloser($text, 0, strlen(rtrim($text)), false) : -1;
    }

    /** The start of the last usable "$$" closer on the line holding $pos, or -1; found once per line. */
    private function boundOnLine(string $text, int $pos): int
    {
        if ($this->lineEnd < 0 || $pos < $this->lineStart || $pos > $this->lineEnd) {
            $before = strrpos(substr($text, 0, $pos), "\n");
            $this->lineStart = $before === false ? 0 : $before + 1;
            $end = strpos($text, "\n", $pos);
            $this->lineEnd = $end === false ? strlen($text) : $end;
            $this->lineBound = $this->lastCloser($text, $this->lineStart, $this->lineEnd, true);
        }

        return $this->lineBound;
    }

    private function lastCloser(string $text, int $start, int $end, bool $followerRule): int
    {
        for ($q = $end - 2; $q > $start; $q--) {
            if ($text[$q] !== '$' || $text[$q + 1] !== '$' || $text[$q - 1] === '\\') {
                continue;
            }
            if ($followerRule && $q + 2 < $end && preg_match('/' . self::WORD . '/', $text[$q + 2]) === 1) {
                continue;
            }

            return $q;
        }

        return -1;
    }

    /** Whether a code span opens inside the $length bytes of formula starting at $from. */
    private function hasCodeSpan(string $text, int $from, int $length): bool
    {
        if (! str_contains(substr($text, $from, $length), '`')) {
            return false;
        }
        foreach ($this->codeOpeners($text) as $offset => $_) {
            if ($offset >= $from && $offset < $from + $length) {
                return true;
            }
        }

        return false;
    }

    /** @return array<int, true> */
    private function codeOpeners(string $text): array
    {
        if ($this->codeOpeners !== null) {
            return $this->codeOpeners;
        }
        preg_match_all('/`+/', $text, $runs, PREG_OFFSET_CAPTURE);
        $runs = $runs[0];
        $byLength = [];
        foreach ($runs as $i => [$run]) {
            $byLength[strlen($run)][] = $i;
        }
        $pointers = [];
        $openers = [];
        for ($i = 0, $count = count($runs); $i < $count; $i++) {
            $length = strlen($runs[$i][0]);
            $pointers[$length] ??= 0;
            while (isset($byLength[$length][$pointers[$length]]) && $byLength[$length][$pointers[$length]] <= $i) {
                $pointers[$length]++;
            }
            $closer = $byLength[$length][$pointers[$length]] ?? null;
            if ($closer !== null) {
                $openers[$runs[$i][1]] = true;
                $i = $closer;
            }
        }

        return $this->codeOpeners = $openers;
    }
}
