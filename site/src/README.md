---
home: true
title: Overview
heroImage: /phactorial-wordmark.svg
heroImageDark: /phactorial-wordmark-dark.svg
heroAlt: Phactorial
heroText: null
tagline: Equations made simple.
actions:
  - text: Get started
    link: /recipes.html
    type: primary
  - text: GitHub
    link: https://github.com/One-Learning-Community/phactorial
    type: secondary
features:
  - title: Plain PHP
    details: Runs in your PHP process and returns a string. No Node, no browser, and the math font is bundled.
  - title: Reads TeX and MathML
    details: Write math in TeX as you would for KaTeX or MathJax, chemistry with \ce, or paste MathML from Word.
  - title: Writes every format
    details: SVG, PNG, MathML, TeX, live Word equations and plain text, all from the same formula.
footer: MIT licensed. Set in STIX Two Math.
---

Phactorial turns math into images and markup from PHP. Give it a formula in TeX or MathML and get back SVG, PNG, MathML, TeX, a Word equation or plain text. It needs PHP 8.4 with the `ctype`, `dom` and `mbstring` extensions. The math font is bundled, so there is nothing else to install.

## Quick start

```
composer require phactorial/phactorial
```

```php
use Phactorial\MathRender;

echo MathRender::fromTex('x = \frac{-b \pm \sqrt{b^2 - 4ac}}{2a}')->display()->svg(pad: 1);
```

![The quadratic formula as SVG](/equation-quadratic.png)

Every glyph is drawn as a path, so the SVG looks the same in every browser and needs no web font.

## Output formats

| Format | Use it for |
|---|---|
| SVG | Web pages and any vector pipeline |
| PNG | Email, chat, anywhere SVG won't show (needs `ext-imagick`) |
| MathML | Browsers that draw math themselves, and screen readers |
| TeX | Storage and editing, or KaTeX and MathJax in the browser |
| Word (OMML) | Word documents, as equations the reader can edit |
| Plain text | Alt text, search indexes, plain-text email |

## How it works

1. **Read.** `Math::tex()` or `Math::mathml()` turns source text into a formula tree. The `Math` builder makes the same tree from PHP code.
2. **Lay out.** `Math::layout()` positions every glyph, following the rules TeX uses.
3. **Write.** A writer turns the result into one of the formats above.

Most code only needs `MathRender`, which runs all three steps in one call. Because every reader produces the same tree, Phactorial also converts between formats: TeX to MathML, MathML to TeX, and either one to Word.

## Compared with KaTeX and MathJax

KaTeX and MathJax are JavaScript libraries. To use them from a PHP site, you run Node next to PHP or let the visitor's browser do the typesetting.

Phactorial understands the same TeX math commands, so most formulas written for KaTeX work here unchanged. It runs inside your PHP process and returns a string. It also writes Word equations, which neither library does. [Input](./input.md#tex) lists the few commands it reads but does not draw.

Not yet supported: line breaks inside long formulas, boxes and backgrounds within a formula, and equation numbers.

## Speed and safety

Once the font is loaded, TeX to SVG takes about 0.1 ms per formula. The first formula in a new PHP process takes about 15 ms without opcache. Time and memory grow linearly with formula size. [`bin/benchmark.php`](https://github.com/One-Learning-Community/phactorial/blob/main/bin/benchmark.php) measures these on your machine.

The readers never throw. Input that is too large or too deep is cut off at fixed limits, drawn as far as it got and reported, so formulas typed by users are safe to render. See [Untrusted input](./recipes.md#untrusted-input).

## Where next

- [Recipes](./recipes.md): formulas in a web page, in email, in Word; converting between formats; alt text; untrusted input.
- [Input](./input.md): the builder, TeX and MathML, and what each reader reports.
- [Output](./output.md): the writers and their options.
- [Custom backends](./backends.md): drawing a layout yourself, and other math fonts.
- [CommonMark](./commonmark.md): math in Markdown, typeset on the server.
