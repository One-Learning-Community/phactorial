<?php

declare(strict_types=1);

namespace Phactorial\CommonMark\Renderer;

use League\Config\ConfigurationInterface;
use Phactorial\CommonMark\MathOutput;
use Phactorial\MathRender;

/** Turns TeX into the HTML a renderer emits; shared by the block, inline and fenced-code renderers. */
final class MathHtml
{
    private ?MathOutput $output = null;
    private float $size = 16.0;

    public function __construct(private readonly ConfigurationInterface $configuration)
    {
    }

    public function render(string $tex, bool $display, bool $block = false): string
    {
        $this->resolve();
        $math = MathRender::fromTex($tex);
        $math = $display ? $math->display() : $math->inline();
        $body = match ($this->output) {
            MathOutput::Mathml => $math->mathml(),
            default => $math->svg($this->size),
        };

        if ($block) {
            return '<div class="math math-display">' . $body . '</div>';
        }

        // Inside a paragraph even display math must be phrasing content; CSS makes .math-display a block.
        return '<span class="math ' . ($display ? 'math-display' : 'math-inline') . '">' . $body . '</span>';
    }

    /** Reads the settings on first use: the configuration is not frozen yet when the extension registers. */
    private function resolve(): void
    {
        if ($this->output !== null) {
            return;
        }
        $this->size = (float) $this->configuration->get('math/size');
        $this->output = MathOutput::from((string) $this->configuration->get('math/output'));
    }
}
