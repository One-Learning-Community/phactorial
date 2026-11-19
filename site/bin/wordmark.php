<?php

declare(strict_types=1);

// Writes the site's logo, favicon source and social card source with the library itself.
// Raster outputs come from rsvg-convert and magick; see the commands at the end of this script.

require __DIR__ . '/../../packages/core/vendor/autoload.php';

use Phactorial\MathRender;

$out = __DIR__ . '/../src/.vuepress/public';
$ink = '#18181b';
$paper = '#fafafa';
$cobalt = '#2350ff';      // the factorial sign on light grounds
$cobaltLight = '#5b7dff'; // the same sign on dark grounds

/** The mark: a bold Ph with the factorial sign in the brand blue. */
function mark(string $blue): string
{
    return '\mathbf{Ph}\textcolor{' . $blue . '}{!}';
}

/**
 * Renders a formula for placing inside a larger SVG.
 *
 * @return array{float, float, float, string} width, height, depth below the baseline, inner markup
 */
function piece(string $tex, float $size, string $color): array
{
    $svg = MathRender::fromTex($tex)->inline()->svg(size: $size, color: $color);
    if (preg_match('/<svg[^>]*width="([\d.]+)"[^>]*height="([\d.]+)"[^>]*>(.*)<\/svg>/s', $svg, $m) !== 1) {
        throw new RuntimeException('Unexpected SVG shape from the renderer.');
    }
    // Inline SVG carries vertical-align: -depth, which puts its baseline on the line's.
    $depth = preg_match('/vertical-align:(-?[\d.]+)px/', $svg, $v) === 1 ? -(float) $v[1] : 0.0;

    return [(float) $m[1], (float) $m[2], $depth, $m[3]];
}

/**
 * Sets pieces side by side on one baseline, with a hairline between them.
 *
 * @return array{float, float, string} width, height, markup
 */
function lockup(array $pieces, float $gap, string $rule): array
{
    $ascent = max(array_map(fn ($p) => $p[1] - $p[2], $pieces));
    $descent = max(array_map(fn ($p) => $p[2], $pieces));
    $x = 0.0;
    $body = '';
    foreach ($pieces as $i => [$w, $h, $d, $inner]) {
        if ($i > 0) {
            $body .= sprintf('<rect x="%.2F" y="0" width="1" height="%.2F" fill="%s" opacity="0.3"/>', $x + $gap / 2 - 0.5, $ascent + $descent, $rule);
            $x += $gap;
        }
        $body .= sprintf('<g transform="translate(%.2F %.2F)">%s</g>', $x, $ascent - ($h - $d), $inner);
        $x += $w;
    }

    return [$x, $ascent + $descent, $body];
}

function svg(float $w, float $h, string $body): string
{
    return sprintf('<svg xmlns="http://www.w3.org/2000/svg" role="img" aria-label="Phactorial" viewBox="0 0 %.2F %.2F" width="%.2F" height="%.2F">%s</svg>', $w, $h, $w, $h, $body);
}

// Site header: the mark beside the roman wordmark, one file per theme.
foreach (['' => [$ink, $cobalt], '-dark' => [$paper, $cobaltLight]] as $suffix => [$fg, $blue]) {
    [$w, $h, $body] = lockup([piece(mark($blue), 40, $fg), piece('\mathrm{Phactorial}', 27, $fg)], 22, $fg);
    file_put_contents("$out/phactorial-wordmark$suffix.svg", svg($w, $h, $body));
}

// The mark alone, for places that already print the name.
[$w, $h, , $body] = piece(mark($cobalt), 64, $ink);
file_put_contents("$out/phactorial-mark.svg", svg($w, $h, $body));

// Favicon: the mark on an ink tile, 64 x 64.
[$w, $h, , $body] = piece(mark($cobaltLight), 30, $paper);
file_put_contents("$out/favicon.svg", sprintf(
    '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 64 64" width="64" height="64"><rect width="64" height="64" rx="14" fill="%s"/><g transform="translate(%.2F %.2F)">%s</g></svg>',
    $ink, (64 - $w) / 2, (64 - $h) / 2, $body,
));

// Social card: 1280 x 640. Stirling's formula with Ph as the variable; the lockup and tagline beneath a hairline.
$p = '\mathbf{Ph}';
[$fw, $fh, , $formula] = piece(
    $p . '\textcolor{' . $cobaltLight . '}{!} \sim \sqrt{2\pi\,' . $p . '}\left(\frac{' . $p . '}{e}\right)^{' . $p . '}',
    92, $paper,
);
[, $lh, $lock] = lockup([piece(mark($cobaltLight), 40, $paper), piece('\mathrm{Phactorial}', 27, $paper)], 22, $paper);
$tag = 'Equations made simple.';
file_put_contents(__DIR__ . '/social-card.svg', sprintf(
    '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1280 640" width="1280" height="640"><rect width="1280" height="640" fill="%s"/>'
    . '<g transform="translate(%.2F %.2F)">%s</g>'
    . '<rect x="80" y="500" width="1120" height="1" fill="%s" opacity="0.18"/>'
    . '<g transform="translate(80 %.2F)">%s</g>'
    . '<text x="1200" y="%.2F" text-anchor="end" font-family="Helvetica Neue, Helvetica, Arial, sans-serif" font-size="30" fill="#a1a1aa">%s</text></svg>',
    $ink, (1280 - $fw) / 2, (500 - $fh) / 2, $formula, $paper, 570 - $lh / 2, $lock, 570 + $lh / 2, $tag,
));

// Rasters (run from site/):
//   rsvg-convert bin/social-card.svg -o src/.vuepress/public/phactorial-social.png
//   rsvg-convert -w 180 src/.vuepress/public/favicon.svg -o src/.vuepress/public/favicon-180.png
//   magick -background none src/.vuepress/public/favicon.svg -define icon:auto-resize=32,16 src/.vuepress/public/favicon.ico
