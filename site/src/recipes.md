# Recipes

Each recipe starts from TeX. `MathRender::fromMathml()` or `MathRender::of()` with a builder formula can take the place of `MathRender::fromTex()`.

## In a web page

Use SVG on the web. It stays sharp at any zoom and needs no math font.

@[code{3-} php](../recipes/php/web-page.php)

`size` is the pixel size of the surrounding text. The color is `currentColor`, the parent's text color, so the formula follows a dark theme; pass `color:` for any other CSS color.

Inline, the formula is in text style, which keeps fractions and limits small, and the SVG carries the `vertical-align` that sets its baseline on the line's. A display formula sits on its own line and carries none.

## The LaTeX look

The look of a LaTeX document is Latin Modern Math. It is not bundled; download `latinmodern-math.otf` from CTAN or the GUST site (GUST Font License). Its outlines are CFF, which the reader handles like any other.

```php
use Phactorial\Font\Font;
use Phactorial\MathRender;

$font = Font::fromFile('/fonts/latinmodern-math.otf');

echo MathRender::fromTex('\int_0^\infty e^{-x^2}\,dx = \frac{\sqrt{\pi}}{2}')->display()->font($font)->svg(size: 18, pad: 2);
```

Load the font once and reuse the object. SVG, PNG and MathML output carry no font, so the file costs nothing there. A PDF that embeds the font carries it whole, about 390 KB; [Quoin](https://one-learning-community.github.io/quoin/guide/fonts.html#the-math-font) does this for equations in its PDF documents.

Other math fonts with CFF outlines load the same way: New Computer Modern Math, the TeX Gyre math fonts, Fira Math, Libertinus Math and XITS Math. In a Quoin document, [Quoin's `withMathFont()` and its Laravel `math_font` option](https://one-learning-community.github.io/quoin/guide/fonts.html#the-math-font) point the font set at the file.

## Accessible formulas

Screen readers can't read paths. `svg()` gives the SVG `role="img"` and an `aria-label` of the formula as linear text:

@[code{5-} php](../recipes/php/accessible.php)

Pass `label:` to write your own. To give screen readers MathML instead, pass an empty label, which marks the SVG `aria-hidden="true"`, and put the MathML beside it, visually hidden:

@[code{5-} php](../recipes/php/accessible-mathml.php)

## In email

Gmail and Outlook do not display SVG. Send PNG. It needs the Imagick extension.

@[code{5-} php](../recipes/php/email.php)

Attach `$png->bytes` to the message as an inline image with the content ID `formula-1`.

`png()` draws two image pixels to each CSS pixel, which stays sharp on high-density screens. `img()` writes the `<img>` at its CSS size, with the formula's text as `alt` and, inline, the `vertical-align` that sets it on the baseline. The same values are on the result as `width`, `height` and `verticalAlign`.

Many email clients block `data:` images, so attach the PNG and reference it by `cid:`, or host it. `dataUri()` serves pages that accept them.

## In Word

`omml()` writes Office Math, the format of Word's equation editor, so readers get live equations they can edit.

@[code{5-} php](../recipes/php/word.php)

The output uses the `m:` and `w:` prefixes without declaring them, so the document root must declare `xmlns:m="http://schemas.openxmlformats.org/officeDocument/2006/math"` beside the usual `w:`. Put display output in a `<w:p>` of its own; it is centered unless you pass `'left'` or `'right'`. Word always sets equations in Cambria Math.

## Converting between formats

Every reader returns the same tree, and every writer takes it.

@[code{5-} php](../recipes/php/converting.php)

TeX from `tex()` reads back as the same formula. MathML details TeX can't express, such as an unusual `mathvariant`, become the nearest TeX.

## Untrusted input

User input can be malformed, huge or hostile. The readers are built for it.

- They never throw. Bad input still gives a formula, drawn as far as it could be read.
- Limits keep the cost fixed. TeX is cut off at 16 KB, 256 levels of nesting, 10,000 macro expansions and 10,000 table cells. MathML is cut off at 1 MB, 16,384 characters of text and 32,768 elements. Stretched delimiters stop at 100 em.
- Everything cut, dropped or drawn differently is reported.

`notes()` returns the reports:

@[code{4-} php](../recipes/php/untrusted.php)

A formula at the limits is still large; its SVG can run to megabytes. If your formulas are short, cap input well below 16 KB.
