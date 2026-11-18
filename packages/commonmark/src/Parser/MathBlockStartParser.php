<?php

declare(strict_types=1);

namespace Phactorial\CommonMark\Parser;

use League\CommonMark\Parser\Block\BlockStart;
use League\CommonMark\Parser\Block\BlockStartParserInterface;
use League\CommonMark\Parser\Cursor;
use League\CommonMark\Parser\MarkdownParserStateInterface;

/** A display block: a line of just $$, or a whole line of $$…$$. */
final class MathBlockStartParser implements BlockStartParserInterface
{
    public function tryStart(Cursor $cursor, MarkdownParserStateInterface $parserState): ?BlockStart
    {
        if ($cursor->isIndented() || $cursor->getNextNonSpaceCharacter() !== '$') {
            return BlockStart::none();
        }
        $line = trim($cursor->getRemainder());
        if ($line === '$$') {
            $cursor->advanceToEnd();

            return BlockStart::of(new MathBlockParser())->at($cursor);
        }
        if (preg_match('/^\$\$(.+)\$\$$/s', $line, $m) === 1 && ! str_contains($m[1], '$$')) {
            $cursor->advanceToEnd();

            return BlockStart::of(new MathBlockParser($m[1]))->at($cursor);
        }

        return BlockStart::none();
    }
}
