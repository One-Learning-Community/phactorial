<?php

declare(strict_types=1);

// Writes the site's wordmark, favicon source and social card source with the library itself.
// Raster outputs come from rsvg-convert and magick; see the commands at the end of this script.

require __DIR__ . '/../../packages/core/vendor/autoload.php';

use Phactorial\MathRender;

$out = __DIR__ . '/../src/.vuepress/public';
$ink = '#18181b';
$paper = '#fafafa';

/** Strips the wrapper so the formula can be placed inside a larger SVG. */
function inner(string $svg): array
{
    if (preg_match('/<svg[^>]*width="([\d.]+)"[^>]*height="([\d.]+)"[^>]*>(.*)<\/svg>/s', $svg, $m) !== 1) {
        throw new RuntimeException('Unexpected SVG shape from the renderer.');
    }

    return [(float) $m[1], (float) $m[2], $m[3]];
}

function wordmark(string $tex, float $size, string $color, float $pad = 2.0): string
{
    return MathRender::fromTex($tex)->display()->svg(size: $size, color: $color, pad: $pad);
}

$word = '\mathrm{Phactorial}';
file_put_contents("$out/phactorial-wordmark.svg", wordmark($word, 28, $ink));
file_put_contents("$out/phactorial-wordmark-dark.svg", wordmark($word, 28, $paper));

// Favicon: "Ph" in a rounded square, 64 x 64.
[$w, $h, $body] = inner(wordmark('\mathrm{Ph}', 34, $paper, 0));
$x = (64 - $w) / 2;
$y = (64 - $h) / 2;
file_put_contents("$out/favicon.svg", sprintf(
    '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 64 64" width="64" height="64"><rect width="64" height="64" rx="14" fill="%s"/><g transform="translate(%.2F %.2F)">%s</g></svg>',
    $ink, $x, $y, $body,
));

// Social card source: 1280 x 640, wordmark over the tagline.
[$w, $h, $body] = inner(wordmark($word, 150, $paper, 0));
$tag = 'TeX and MathML to SVG, MathML and Word, in plain PHP.';
file_put_contents(__DIR__ . '/social-card.svg', sprintf(
    '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1280 640" width="1280" height="640"><rect width="1280" height="640" fill="%s"/><g transform="translate(%.2F %.2F)">%s</g><text x="640" y="%.2F" text-anchor="middle" font-family="Helvetica Neue, Helvetica, Arial, sans-serif" font-size="38" fill="#a1a1aa">%s</text></svg>',
    $ink, (1280 - $w) / 2, 215, $body, 215 + $h + 80, $tag,
));

// Rasters (run from site/):
//   rsvg-convert bin/social-card.svg -o src/.vuepress/public/phactorial-social.png
//   rsvg-convert -w 180 src/.vuepress/public/favicon.svg -o src/.vuepress/public/favicon-180.png
//   magick -background none src/.vuepress/public/favicon.svg -define icon:auto-resize=32,16 src/.vuepress/public/favicon.ico
