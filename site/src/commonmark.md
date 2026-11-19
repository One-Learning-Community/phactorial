# CommonMark

`phactorial/commonmark` adds GitHub's math syntax to [league/commonmark](https://commonmark.thephpleague.com/) and typesets each formula on the server, as SVG or MathML. The page needs no JavaScript and no math font.

## Install

```
composer require phactorial/commonmark
```

It needs PHP 8.4 or newer.

## Use

```php
use League\CommonMark\Environment\Environment;
use League\CommonMark\Extension\CommonMark\CommonMarkCoreExtension;
use League\CommonMark\MarkdownConverter;
use Phactorial\CommonMark\MathExtension;

$environment = new Environment(['math' => ['output' => 'svg']]);
$environment->addExtension(new CommonMarkCoreExtension());
$environment->addExtension(new MathExtension());

echo (new MarkdownConverter($environment))->convert('Area is $\pi r^2$.');
```

Using CommonMark in Laravel? See the dedicated [Laravel](./laravel.md) page.

## Syntax

The rules follow GitHub's renderer, so Markdown that renders math on GitHub renders it here.

| Markdown | Result |
|---|---|
| `$x^2$` | Inline math |
| ``$`e^{i\pi}`$`` | Inline math that may hold a `$` |
| `$$` on its own lines around TeX | A display block |
| ```` ```math ```` fence | A display block |
| `\$` | A literal dollar sign |

Prices stay prices: `costs $5 and $10` is plain text. Code spans and link text are never math.

## Options

All options live under the `math` key.

| Key | Values | Default | Meaning |
|---|---|---|---|
| `output` | `svg`, `mathml` | `svg` | SVG looks the same in every browser. MathML is smaller, selectable, and drawn by the browser. |
| `size` | a number above 0 | `16` | Font size in pixels for SVG output. |
| `on_error` | `source`, `render` | `source` | A formula with an unknown command shows its Markdown in `<code class="math math-error">`, or with `render`, is drawn from what could be read. |
| `latex_delimiters` | `true`, `false` | `false` | Also read `\(…\)` and `\[…\]`, the way LaTeX and chatbots write math. Off by default because `\[citation needed\]` would become a formula. |

To center display math written inside a paragraph, add:

```css
.math-display { display: block; text-align: center; }
```

The HTML shapes, class names and options are stable; see [Stability](./stability.md#the-commonmark-extension).

## Accessibility

Each SVG formula carries `role="img"` and an `aria-label` that spells the formula out as text, so screen readers speak it. MathML output is text already, and screen readers read it directly. [Accessible formulas](./recipes.md#accessible-formulas) has more.
