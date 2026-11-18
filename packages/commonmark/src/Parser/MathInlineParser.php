<?php

declare(strict_types=1);

namespace Phactorial\CommonMark\Parser;

use League\CommonMark\Parser\Cursor;
use League\CommonMark\Parser\Inline\InlineParserInterface;
use League\CommonMark\Parser\Inline\InlineParserMatch;
use League\CommonMark\Parser\InlineParserContext;
use Phactorial\CommonMark\Node\MathInline;

/**
 * GitHub's inline forms: $`…`$, $$…$$ (display) and $…$. A single dollar opens only before a non-space and closes
 * only after a non-space that is not followed by a digit, so "costs $5 and $10" stays text. It never spans a
 * backtick, which leaves code spans to the code parser.
 *
 * An opener with no closer after it must not rescan the paragraph, or a paragraph of unclosed openers parses in
 * quadratic time. The last closer's position is found once per paragraph and compared with each opener's position.
 */
final class MathInlineParser implements InlineParserInterface
{
    private const array FORMS = [
        ['/^\$`(.+?)`\$/s', false],
        ['/^\$\$(.+?)\$\$/s', true],
        ['/^\$(?![\s$])((?:\\\\.|[^\\\\$`])+?)(?<!\s)\$(?!\d)/s', false],
    ];

    /** @var \WeakReference<Cursor>|null The cursor whose paragraph the cached closer position belongs to. */
    private ?\WeakReference $closerCursor = null;
    private int $lastCloser = -1;

    public function getMatchDefinition(): InlineParserMatch
    {
        return InlineParserMatch::string('$');
    }

    public function parse(InlineParserContext $inlineContext): bool
    {
        $cursor = $inlineContext->getCursor();
        $rest = $cursor->getRemainder();
        $backtickForm = $cursor->getBytePosition() + 2 <= $this->lastBacktickCloser($cursor);
        foreach (self::FORMS as $i => [$pattern, $display]) {
            if ($i === 0 && ! $backtickForm) {
                continue;
            }
            if (preg_match($pattern, $rest, $m) === 1) {
                $cursor->advanceBy(mb_strlen($m[0], 'UTF-8'));
                $inlineContext->getContainer()->appendChild(new MathInline($m[1], $display));

                return true;
            }
        }

        return false;
    }

    /** The byte offset of the last "`$" in the cursor's paragraph, or -1; found once per cursor. */
    private function lastBacktickCloser(Cursor $cursor): int
    {
        if ($this->closerCursor?->get() !== $cursor) {
            $position = strrpos($cursor->getLine(), '`$');
            $this->lastCloser = $position === false ? -1 : $position;
            $this->closerCursor = \WeakReference::create($cursor);
        }

        return $this->lastCloser;
    }
}
