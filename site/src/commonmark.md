# CommonMark

`phactorial/commonmark` adds GitHub's math syntax to [league/commonmark](https://commonmark.thephpleague.com/) and typesets each formula on the server, as SVG or MathML. The page needs no JavaScript and no math font.

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

In Laravel, pass the extension and its options to `Str::markdown()`. [Laravel](./laravel.md) covers mail templates, spatie/laravel-markdown and caching too.

```php
use Illuminate\Support\Str;
use Phactorial\CommonMark\MathExtension;

Str::markdown($text, ['math' => ['output' => 'mathml']], [new MathExtension()]);
```

The extension works alongside `GithubFlavoredMarkdownExtension`, which `Str::markdown()` loads.

## Syntax

The rules match GitHub's renderer.

| Markdown | Result |
|---|---|
| `$x^2$` | Inline math. The opening `$` must be followed by a non-space and not preceded by an ASCII letter, digit, underscore or `$`, or by a non-ASCII character. The closing `$` must not be preceded by a backslash or followed by an ASCII letter, digit or underscore, so `costs $5 and $10` stays text. |
| ``$`e^{i\pi}`$`` | Inline math that may contain any `$` and may span lines. |
| `$$\sum k$$` inside a line of text | Inline math, on one line. |
| `$$` alone on a line, then TeX, then `$$` | A display block, when the paragraph starts and ends with `$$`. It works inside block quotes and list items, and the TeX may span lines. |
| A paragraph of only `$$…$$` | A display block. |
| ```` ```math ```` fence | A display block. Other fences are left alone. |
| `` `$x$` `` and other code | Code, never math. A code span inside a formula ends the formula. |
| `\$` | A literal dollar sign. |
| `[link $x$](…)` | Plain text. GitHub does not typeset math inside link text. |

With `latex_delimiters` on, two more forms are math. `\(…\)` is inline math on one line. `\[…\]` is display math that may span lines, and a paragraph holding only one becomes a display block. A `\)` or `\]` closes the formula only when its own backslash is not escaped, so `\[a\\]` stays text. An unclosed opener, blank TeX or a code span inside leaves the backslash an ordinary escape: `\(x` reads `(x`.

## Output

Inline math becomes `<span class="math math-inline">` around an `<svg>` or `<math>` element. Each `<svg>` has `role="img"` and an `aria-label` that spells the formula out as text, so screen readers speak it; see [Accessible formulas](./recipes.md#accessible-formulas). A `$$…$$` formula inside a paragraph is `<span class="math math-display">`, and a display block is `<div class="math math-display">`. Add this rule to your CSS to center display math that sits inside a paragraph:

```css
.math-display { display: block; text-align: center; }
```

A formula the reader could not fully parse takes a different form, `<code class="math math-error">` or `<div class="math math-display math-error">`; see [Errors](#errors).

## Options

All options live under the `math` key.

| Key | Values | Default | Meaning |
|---|---|---|---|
| `output` | `svg`, `mathml` | `svg` | `svg` draws each formula as an inline `<svg>` that looks the same everywhere. `mathml` writes MathML Core for the browser to draw. |
| `size` | a number above 0 | `16` | The font size in pixels for SVG output. |
| `on_error` | `source`, `render` | `source` | What a formula the reader could not fully parse becomes. `source` shows the formula's Markdown unchanged. `render` draws what the reader recovered. |
| `latex_delimiters` | `true`, `false` | `false` | `true` also reads `\(…\)` as inline math and `\[…\]` as display math, the way LaTeX and chatbots write them. It is off by default because `\(` and `\[` are Markdown's escaped parentheses and brackets: with it on, `\[citation needed\]` becomes a formula. |

An invalid value throws `League\Config\Exception\ValidationException` on the first render.

## SVG or MathML

SVG output looks the same in every browser, and the glyphs are paths, so no font loads. It costs bytes: each formula carries its outlines, and the text is not selectable. Gmail and Outlook remove SVG from email; [Laravel's mail recipe](./laravel.md#mail) sends PNG instead.

MathML output is small and stays text, so readers can select it and screen readers can speak it. The browser draws it, so its look depends on the browser and the math font it finds. Current Chrome, Firefox and Safari all render MathML Core. Choose SVG when the look must match everywhere, and MathML when size and accessibility matter more.

## Errors

A formula is an error when the reader dropped part of it, as it does for an unknown command such as `\nosuchcommand`.

With `on_error: source`, the default, the page shows the formula's Markdown, delimiters included and HTML-escaped, instead of a drawing:

- An inline formula becomes `<code class="math math-error">$\nosuchcommand{x}$</code>`.
- A display block keeps its block shape: `<div class="math math-display math-error"><code>$$…$$</code></div>`.
- A `math` fence shows its content between `$$` delimiters.

Style `.math-error` as you like. With `on_error: render`, the formula is drawn from whatever the reader recovered.

A formula the reader only approximated is drawn either way. The extension never emits a link from math, and the source fallback is escaped like any other text.

## What stays fixed

These parts are the extension's contract. They change only in a major release, apart from additions:

- the option keys and values above; a minor release may add keys and values;
- the HTML shapes under [Output](#output) and [Errors](#errors), including the class names;
- a fence whose first info word is `math` is typeset, and every other fence goes to the next renderer;
- a paragraph that is only `$$…$$`, or only `\[…\]` with `latex_delimiters` on, becomes a display block, and math inside link text or image alt text returns to text: dollar math keeps its source, and `\(…\)` or `\[…\]` reads as it would with the option off;
- the priorities: the dollar parser registers at 50, the parser for `\(` and `\[` at 90, ahead of league/commonmark's escape parser at 80, the `FencedCode` renderer at 50, and the `MathInline` and `MathBlock` renderers at the default 0. A renderer registered at a higher number wins, which is how [Laravel's mail recipe](./laravel.md#mail) overrides them;
- the node properties `MathInline::$tex`, `$display` and `$source`, and `MathBlock::$tex` and `$source`. They are read-only.

The wiring classes behind these (the parser, the renderers, the listener and the output helper) are internal. Register `MathExtension` and nothing else. See [Stability](./stability.md#the-commonmark-extension).

## Compatibility with GitHub

The syntax rules are pinned to GitHub's own renderer. `bin/capture-github.php` sends a list of sample strings to the `/markdown` API and saves the HTML under `tests/fixtures/github`. `GithubParityTest` compares the formulas GitHub marks, and the text around them, with this extension's output. These cases differ, and the test group `known-divergence` holds them:

- `$$a$$b$$`: GitHub reads one formula and the text `b`, and drops the trailing `$$`. This extension keeps the `$$` as text.
- GitHub puts a display formula inside a `<p>`. This extension emits a `<div>` for a paragraph that holds only that formula, with the same formula inside.

A pipe inside a table cell splits the cell in both renderers, because tables split before inline parsing: `$a|b$` becomes the cells `$a` and `b$`.
