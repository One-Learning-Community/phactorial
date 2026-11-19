<?php

declare(strict_types=1);

// Renders the CommonMark page's examples with the extension itself and writes them
// between the examples markers in site/src/commonmark.md. Run it again after a change
// to the examples or the renderer.

require __DIR__ . '/../recipes/vendor/autoload.php';

use League\CommonMark\Environment\Environment;
use League\CommonMark\Extension\CommonMark\CommonMarkCoreExtension;
use League\CommonMark\MarkdownConverter;
use Phactorial\CommonMark\MathExtension;

$examples = [
    'Euler\'s identity, $e^{i\pi} + 1 = 0$, ties five constants together.',
    "\$\$\n\\int_0^\\infty e^{-x^2}\\,dx = \\frac{\\sqrt{\\pi}}{2}\n\$\$",
    "```math\n\\sum_{k=1}^{n} k = \\frac{n(n+1)}{2}\n```",
];

$environment = new Environment(['math' => ['output' => 'svg', 'size' => 18]]);
$environment->addExtension(new CommonMarkCoreExtension());
$environment->addExtension(new MathExtension());
$converter = new MarkdownConverter($environment);

// One HTML block with no blank lines, so the page's Markdown passes it through untouched;
// v-pre keeps Vue from reading any braces in it.
$html = "<div class=\"md-examples\" v-pre>\n";
foreach ($examples as $markdown) {
    $rendered = str_replace("\n", '', trim($converter->convert($markdown)->getContent()));
    $html .= '<div class="md-example"><pre><code>' . htmlspecialchars($markdown) . '</code></pre>'
        . '<div class="md-result">' . $rendered . "</div></div>\n";
}
$html .= '</div>';

$page = __DIR__ . '/../src/commonmark.md';
$start = '<!-- examples:start -->';
$end = '<!-- examples:end -->';
$text = file_get_contents($page);
$from = strpos($text, $start);
$to = strpos($text, $end);
if ($from === false || $to === false) {
    fwrite(STDERR, "The examples markers are missing from {$page}.\n");
    exit(1);
}
$text = substr($text, 0, $from + strlen($start)) . "\n" . $html . "\n" . substr($text, $to);
file_put_contents($page, $text);
echo "Wrote " . count($examples) . " examples to {$page}.\n";
