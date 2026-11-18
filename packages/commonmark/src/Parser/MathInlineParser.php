<?php

declare(strict_types=1);

namespace Phactorial\CommonMark\Parser;

use League\CommonMark\Parser\Inline\InlineParserInterface;
use League\CommonMark\Parser\Inline\InlineParserMatch;
use League\CommonMark\Parser\InlineParserContext;
use Phactorial\CommonMark\Node\MathInline;

/**
 * GitHub's inline forms: $`…`$, $$…$$ (display) and $…$. A single dollar opens only before a non-space and closes
 * only after a non-space that is not followed by a digit, so "costs $5 and $10" stays text. It never spans a backtick, which leaves code spans to the code parser.
 */
final class MathInlineParser implements InlineParserInterface
{
    private const array FORMS = [
        ['/^\$`(.+?)`\$/s', false],
        ['/^\$\$(.+?)\$\$/s', true],
        ['/^\$(?![\s$])((?:\\\\.|[^\\\\$`])+?)(?<!\s)\$(?!\d)/s', false],
    ];

    public function getMatchDefinition(): InlineParserMatch
    {
        return InlineParserMatch::string('$');
    }

    public function parse(InlineParserContext $inlineContext): bool
    {
        $cursor = $inlineContext->getCursor();
        $rest = $cursor->getRemainder();
        foreach (self::FORMS as [$pattern, $display]) {
            if (preg_match($pattern, $rest, $m) === 1) {
                $cursor->advanceBy(mb_strlen($m[0], 'UTF-8'));
                $inlineContext->getContainer()->appendChild(new MathInline($m[1], $display));

                return true;
            }
        }

        return false;
    }
}
