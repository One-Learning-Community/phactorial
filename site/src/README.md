# Phactorial

Phactorial typesets mathematics in plain PHP. It reads TeX or MathML and writes SVG, PNG, MathML, TeX, Word equations (OMML) or plain text. It needs PHP 8.4 with `ctype`, `dom` and `mbstring`. No Node, no browser, no fonts on the server.

```
composer require phactorial/phactorial
```

```php
use Phactorial\MathRender;

echo MathRender::fromTex('x = \frac{-b \pm \sqrt{b^2 - 4ac}}{2a}')->display()->svg(pad: 1);
```

![The quadratic formula as SVG](/equation-quadratic.png)

Every glyph is a path, so the SVG looks the same everywhere and needs no web font.

## Reads and writes

It reads TeX (including `\ce` and `\pu` chemistry), MathML, MathType and Wiris text, and the `Math` builder in PHP. It writes the formats in the table below. Every reader produces the same tree, so Phactorial also converts: TeX to MathML, MathML to TeX, either to Word.

## What it does

A formula passes through three stages.

1. **Read.** `Math::tex()` and `Math::mathml()` turn source text into a formula tree. The `Math` builder makes the same tree in PHP.
2. **Lay out.** `Math::layout()` sets the tree in an OpenType math font, by the rules TeX and MathML Core use.
3. **Write.** A writer turns the tree or the layout into output.

`MathRender` runs all three in one call. The classes behind it stay public for finer control.

| Output | From | Use it for |
|---|---|---|
| SVG | the layout | Web pages, any vector pipeline |
| PNG | the layout | Email, chat, anything that won't show SVG (needs ext-imagick) |
| MathML | the tree | Browsers that render MathML themselves, screen readers |
| TeX | the tree | Editing, storage, KaTeX or MathJax on the client |
| OMML | the tree | Word documents, as live equations a reader can edit |
| Plain text | the tree | Alt text, search indexes, plain-text email |

## Compared with KaTeX and MathJax

KaTeX and MathJax are the standard tools for math on the web, and they are excellent. Both are JavaScript, so a PHP site runs Node beside PHP or renders in the visitor's browser.

Phactorial is the PHP-only alternative. It reads the TeX KaTeX reads (gaps listed under [Input](./input.md#tex)), runs in your PHP process and returns a string. It also writes Word equations, which neither JavaScript library does.

phpoffice/math converts between MathML and Word's format but does not typeset. Phactorial also reads TeX and draws the formula.

Not yet supported: line breaks inside long formulas, boxes and backgrounds within a formula, and equation numbers.

## Speed and safety

Once the font is loaded, TeX to SVG takes about 0.1 ms per formula. The first formula in a new PHP process takes about 15 ms without opcache, with classes and font loaded from scratch. Time and memory grow linearly with formula size. [`bin/benchmark.php`](https://github.com/One-Learning-Community/phactorial/blob/main/bin/benchmark.php) in the repository measures these on your machine.

The readers never throw. Input that is too large or too deep is cut off at fixed limits, drawn as far as it got and reported, so formulas typed by users are safe to render. See [Untrusted input](./recipes.md#untrusted-input).

## Where next

- [Recipes](./recipes.md): formulas in a web page, in email, in Word; converting between formats; alt text; untrusted input.
- [Input](./input.md): the builder, TeX and MathML, and what each reader reports.
- [Output](./output.md): the writers and their options.
- [Custom backends](./backends.md): drawing a layout yourself, and other math fonts.
- [CommonMark](./commonmark.md): math in Markdown, typeset on the server.
- Quoin uses Phactorial for [equations in PDF, Word and HTML documents](https://one-learning-community.github.io/quoin/guide/equations.html).
