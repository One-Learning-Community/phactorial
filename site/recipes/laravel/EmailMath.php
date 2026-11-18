<?php

namespace App\Markdown;

use Illuminate\Support\Facades\Storage;
use League\CommonMark\Environment\EnvironmentBuilderInterface;
use League\CommonMark\Extension\CommonMark\Node\Block\FencedCode;
use League\CommonMark\Extension\ExtensionInterface;
use League\CommonMark\Node\Node;
use League\CommonMark\Renderer\ChildNodeRendererInterface;
use League\CommonMark\Renderer\NodeRendererInterface;
use Phactorial\CommonMark\Node\MathBlock;
use Phactorial\CommonMark\Node\MathInline;
use Phactorial\MathRender;

final class EmailMath implements ExtensionInterface, NodeRendererInterface
{
    public function register(EnvironmentBuilderInterface $environment): void
    {
        $environment
            ->addRenderer(MathInline::class, $this, 10)
            ->addRenderer(MathBlock::class, $this, 10)
            ->addRenderer(FencedCode::class, $this, 100);
    }

    public function render(Node $node, ChildNodeRendererInterface $childRenderer): ?string
    {
        [$tex, $display] = match (true) {
            $node instanceof MathInline => [$node->tex, $node->display],
            $node instanceof MathBlock => [$node->tex, true],
            $node instanceof FencedCode && ($node->getInfoWords()[0] ?? '') === 'math' => [trim($node->getLiteral()), true],
            default => [null, false],
        };

        if ($tex === null) {
            return null;
        }

        $math = MathRender::fromTex($tex);
        $png = ($display ? $math->display() : $math->inline())->png(pad: 1);

        $path = 'math/' . hash('sha256', $png->bytes) . '.png';
        Storage::disk('public')->put($path, $png->bytes);
        $img = $png->img(Storage::disk('public')->url($path));

        return $node instanceof MathInline ? $img : '<p style="text-align: center">' . $img . '</p>';
    }
}
