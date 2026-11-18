<?php

declare(strict_types=1);

namespace Phactorial\CommonMark\Parser;

use League\CommonMark\Parser\Block\AbstractBlockContinueParser;
use League\CommonMark\Parser\Block\BlockContinue;
use League\CommonMark\Parser\Block\BlockContinueParserInterface;
use League\CommonMark\Parser\Cursor;
use Phactorial\CommonMark\Node\MathBlock;

final class MathBlockParser extends AbstractBlockContinueParser
{
    private MathBlock $block;
    /** @var list<string> */
    private array $lines = [];
    private bool $closed;

    public function __construct(?string $oneLine = null)
    {
        $this->block = new MathBlock();
        if ($oneLine !== null) {
            $this->lines[] = $oneLine;
        }
        $this->closed = $oneLine !== null;
    }

    public function getBlock(): MathBlock
    {
        return $this->block;
    }

    public function tryContinue(Cursor $cursor, BlockContinueParserInterface $activeBlockParser): ?BlockContinue
    {
        if ($this->closed) {
            return BlockContinue::none();
        }
        $line = rtrim($cursor->getRemainder());
        if (str_ends_with($line, '$$')) {
            $before = substr($line, 0, -2);
            if (trim($before) !== '') {
                $this->lines[] = $before;
            }
            $this->closed = true;

            return BlockContinue::finished();
        }

        return BlockContinue::at($cursor);
    }

    public function addLine(string $line): void
    {
        $this->lines[] = $line;
    }

    public function closeBlock(): void
    {
        $this->block->tex = trim(implode("\n", $this->lines));
    }
}
