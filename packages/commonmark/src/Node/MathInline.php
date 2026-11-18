<?php

declare(strict_types=1);

namespace Phactorial\CommonMark\Node;

use League\CommonMark\Node\Inline\AbstractInline;

final class MathInline extends AbstractInline
{
    public function __construct(public readonly string $tex, public readonly bool $display = false)
    {
        parent::__construct();
    }
}
