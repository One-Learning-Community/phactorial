# Changelog

All notable changes are recorded here. The format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and the project follows [Semantic Versioning](https://semver.org/).

## [Unreleased]

## [0.20.0] - 2026-11-18

### Added

- Color in formulas. `\color`, `\textcolor` and MathML `mathcolor` are drawn by SVG and PNG, written by the MathML and TeX writers, and carried into Word equations as `w:color`. The tree gains `Node\Color`.
- `Glyph::$color` and `Rule::$color`, each a `?string` that is null or a lowercase `#rrggbb`, and `withColor()` on both. A backend that ignores them draws one color, as before. Both `withColor()` methods throw `InvalidArgumentException` for anything but a lowercase `#rrggbb`.
- MathML color on a delimiter that pairs into a fence is not drawn, and the reader notes it: "color on a paired delimiter is not drawn".

### Changed

- Word output with a `RunFormat` color now carries `m:ctrlPr`, so fraction bars, radicals and brackets take that color as well as the runs.
- The TeX writer keeps the spacing of a color switch.
- `\mathop{\int}` and `\mathop{\sum}` space as `\int` and `\sum` do, as in TeX, where they used to take extra class spacing. They write back as `\int\limits` and `\sum`.
- A script on a lone colored token is written without braces: `\textcolor{red}{x}^2`, not `{\textcolor{red}{x}}^2`. A colored `\sum` is written bare when its limits are stacked, and braced when its scripts sit beside it, since a bare one would take them as limits. A colored integral takes its scripts beside it and is never braced.
- Postfix operators such as `!` and `%` no longer take a space in text output: `n! ∼ √(2πn)`, `5% ⋅ 3`.
- MathML written by this library reads back with the same spacing. A zero `lspace` or `rspace` that the TeX spacing rules produce is read as that rule. Other zeros are kept, so `\mathord{=}` stays `\mathord{=}`, except alone inside a fence, where it reads as `=` with the same layout. One case is ambiguous: `\mathrel` on a symbol that is not a relation (such as `\mathrel{:}`) that loses its space on both sides reads back as `\mathord`.
- A label-less chemistry arrow beside a relation, a punctuation mark or a fence is written between empty groups, as mhchem writes it, so it reads back at its own width and not as a relation.
- Goldens, each changed on purpose:
  - The TeX corpus entries `color` and `color-more` expect no notes, since color is drawn.
  - `TexEnvironmentTest` now expects no notes for accepted values, and it and `TexFinalReviewTest` check the rejected-color note with `red!50`, because `red` is now accepted.
  - `TexSourceTest` expects `\frac{\boxed{a}}{\color{red} b}`; the doubled braces around the switch are gone.
  - `MathmlWriterTest` gains a row that shows an inline color switch written per item.
  - `OmmlWriterTest::test_run_format` counts the `m:ctrlPr` of each object along with the runs.
  - The `binomial` MathML snapshot writes the `!` before `(` as `form="postfix"` and the `(` without a form, as in `k(n-k)`.
  - The `cd-env` TeX MathML snapshot writes the `>` after `@` or another `>` without `form="prefix"`, since a relation after an operator is infix, and writes `rspace="0em"` and `lspace="0em"` between the two `>`, which sit side by side.
  - The layout of these TeX corpus entries changed; no snapshot pins them. `stirling` is wider, since `!` ends an operand (72.8 to 79.4 at 12pt). `arrows-more` (230.3 to 163.7), `arrows-long` (122.1 to 95.4), `arrows-harpoon` (72.9 to 52.9), `delims-arrows` (84.1 to 50.7), `symbols-logic` (141.7 to 135.1), `symbols-geometry` (179.4 to 172.8) and `cd-env` (240.5 to 227.2) are narrower, because adjacent relations take no space between them; their runs of relations also lose `form="prefix"` and gain `lspace="0em"` and `rspace="0em"`. In `escapes` (`\$ 5 \% \& \# \_ \{ \}`), `%` and `&` are now postfix and `#` infix, so the width goes from 68.8 to 64.8 and the text from `$5 % &#_{}` to `$5%& # _{}`.
  - The text output of about 80 corpus and fixture entries changed. A fence takes no padding (`f ( x)` is `f(x)`, `Fe ( OH)₃` is `Fe(OH)₃`, `P ( A ∣ B)` is `P(A ∣ B)`). Space commands and adjacent operators no longer double a space (`a b  c d` is `a b c d`, `kg  ⋅ m  /  s²` is `kg ⋅ m / s²`). Runs of relations are set off by spaces (`↑↓↕⇑⇓⇕` is `↑ ↓ ↕ ⇑ ⇓ ⇕`, `⇌⇀↽⇋` is `⇌ ⇀ ↽ ⇋`).
  - `ChemScriptsTest` expects `[⁶⁰Co(NH₃)₆]³⁺`, with no padding inside the fence.

### Fixed

- A relation or binary operator after a postfix operator or another relation was set as prefix and lost its spacing: `n! \sim b`, `n! + b`, `x \sim \sim b`. `!`, `%`, primes and the degree sign now end an operand. `%` before an opening fence stays binary: `a \% (b + c)`.
- Spacing now follows TeX where TeX sets none: between adjacent relations (`\to\to`), between a fence and a relation (`\left( = b \right)`, `\{ \sim \}`), and before punctuation after a relation (`a =, b`). Space commands between relations are skipped, as TeX skips glue. `\mathrel` sets only the class, so `\mathrel{:}=` closes up too. An explicit MathML `lspace` or `rspace` other than the thick space still wins.
- Text output no longer doubles spaces between adjacent operators or space commands (`a\,=\,b` is `a = b`) or pads fences (`f(x)`, `k!(n − k)!`).
- A `#rrggbb` value in `\color` lost the `#` and its first digit on a round trip.
- A `\tag` after a color switch was dropped.
- A color switch as a script or an argument gained braces on every round trip.
- `\over` after a color switch split the group wrongly.
- A color switch inside `\mathrm{…}` and other font or class arguments leaked out on a round trip.
- `\textcolor{red}{\sum}_i^n` in display style lost its limits above and below, and the TeX writer put `\nolimits` after a colored operator, which LaTeX and KaTeX reject.
- `\mathbin{\textcolor{red}{x}}` and the other class commands lost their class around a color.
- MathML `mathcolor` on `mtr` and `mtd` was ignored.
- `a \color{red} + b` is spaced like `a + b`, also when the color is rejected. A braced group, `a {\color{red} + b}`, is one atom as in TeX, and is spaced like `a {+ b}`.
- `\over` after a `\middle.` inside a color switch took the whole group as its numerator.
- `\left( \color{red} a \middle| b \right)` was written back as `\left( {\color{red} a} \middle| b \right)`, which narrowed the switch to the part before the `\middle`.

## [0.19.0] - 2026-11-18

### Fixed

- A ` ```math ` fence is typeset when a code highlighter such as Phiki or spatie/laravel-markdown's is installed, instead of being highlighted as code. Other fences still go to the highlighter.

### Changed

Breaking. Each item says how to migrate.

- **`Math::xarrow()` takes the label below before the label above: `xarrow($arrow, $under, $over)`. A positional call written for 0.18 now swaps its labels. Swap the two arguments, or pass them by name.** The TeX and chemistry readers place their labels as before.
- `OmmlWriter::oMathPara()` defaults to `'center'`, where it defaulted to `'left'`. Pass `'left'` to keep the old alignment. It matches `MathRender::omml()`.
- Parameters were renamed to match the documentation. A call by position is unaffected; a call by name needs the new name, and the old name throws an `Error`.
  - `Math::frac($num, $den)` is `frac($numerator, $denominator)`.
  - `Math::sup($base, $sup)` is `sup($base, $superscript)`, and `sub($base, $sub)` is `sub($base, $subscript)`.
  - `Math::subSup($base, $sub, $sup)` is `subSup($base, $subscript, $superscript)`.
  - `Math::bigOp($op, …)` is `bigOp($operator, …)`.
  - `Math::big($delimiter, $size)` is `big($delimiter, $step)`.
  - `Math::notes($root)` is `notes($node)`.
  - `Font::gid($cp)` is `gid($codePoint)`.
  - `Box::each($fn)` is `each($visit)`.
  - `SafeXml::decode($s)` is `decode($text)`.
  - `OmmlWriter::oMathPara($root, $jc, …)` is `oMathPara($root, $align, …)`.
- The classes `Math`, `MathmlReader`, `TexReader`, `SafeXml`, `MathmlWriter`, `OmmlWriter`, `TexWriter` and `TextWriter` have private constructors. Call their static methods: `new MathmlWriter()` becomes `MathmlWriter::write()`.
- `SvgRenderer::glyphPaths()` takes the extent instead of a box: `glyphPaths($glyphs, $width, $ascent, $descent)`. Replace `glyphPaths($glyphs, $box)` with `glyphPaths($glyphs, $box->width, $box->ascent, $box->descent)`.
- `Font::$unitsPerEm` and `Font::$numGlyphs` are read-only. `Box::$items` is read-only and cannot be assigned; read it or call `each()`.
- `MathBlock::$tex` and `MathBlock::$source` in `phactorial/commonmark` are read-only. Read them; the parser sets them.
- `TexWriter::write()` throws `InvalidArgumentException`, naming the class, for a node it does not know. The other writers already did.
- `OmmlWriter::checkAlignment()` is internal. Pass the alignment to `oMathPara()` or `MathRender::omml()`, which check it.
- These are marked `@internal` and may change in any release: the constructors of `MathNote`, `MathPng`, `MathmlResult`, `TexResult`, `MissingExtension`, `UnsupportedFont`, `Box`, `Glyph`, `Rule` and every node class; `MissingExtension::imagick()`, `UnsupportedFont::cff()` and `UnsupportedFont::noMathTable()`; `Box::$italic` and `Box::$simple`; `Identifier::$italic`; `TexSource::$tex` and `TexSource::$close`; the `Grid` constants; `Space::MAX_EM`; `Operator::isLargeop()` and `Operator::hasMovableLimits()`. Build formulas with `Math`, `MathRender` and the readers.
- In `phactorial/commonmark`, the parser, the renderers, the listener and the output helper (`MathInlineParser`, `MathNodeRenderer`, `MathHtml`, `MathBlockListener`, `MathOutput`) are internal. Register `MathExtension` and use `MathInline` and `MathBlock` for their properties; their constructors are internal too.

### Added

- A `latex_delimiters` option in `phactorial/commonmark`. Set to `true`, it reads `\(…\)` as inline math and `\[…\]` as display math, the way LaTeX and chatbots write them. It is off by default, because `\[` and `\(` are Markdown's escaped brackets and parentheses, and turning it on would change existing documents.
- `Node::children()` is public. It returns a node's children in reading order, so a walker can recurse through a tree without knowing every node type.
- `Glyph::translated()` and `Rule::translated()` are public, through `DisplayItem::translated()`.
- [Stability](https://one-learning-community.github.io/phactorial/stability.html) states the compatibility policy for 1.0: what a minor release may add, what breaks, the sealed `Node` and `DisplayItem`, the closed set of display items, and the output, defaults and coverage rules.

## [0.18.0] - 2026-11-18

### Added

- `phactorial/commonmark` adds GitHub's math syntax to league/commonmark: `$…$`, ``$`…`$``, `$$…$$` and ` ```math ` fences, typeset on the server as SVG or MathML. See [the CommonMark page](https://one-learning-community.github.io/phactorial/commonmark.html).
- `MathRender` reads a formula and writes it in one call: `MathRender::fromTex('\pi r^2')->svg()`. It starts in the style the source asked for, and `display()`, `inline()` and `font()` change the style and font. `svg()` adds `role="img"`, an `aria-label` of the formula's text, and inline, the `vertical-align` that sets the baseline. `png()` returns a `MathPng` with the CSS size, the baseline offset, `dataUri()` and `img()`. `mathml()`, `omml()`, `tex()` and `text()` write the other formats, and `notes()` returns the reader's reports. Its defaults are inline, 16 pixels and `currentColor`.
- `PngRenderer::pixelSize()` gives the size in pixels of the image `render()` draws, and `OmmlWriter::checkAlignment()` checks an equation alignment. See [Output](https://one-learning-community.github.io/phactorial/output.html#quick-output).

### Changed

- The math library left Quoin and is now Phactorial: the package `quoin/math` is `phactorial/phactorial`, and the namespace `Quoin\Math` is `Phactorial`.

### Removed

- The deprecated `TrueTypeFont` alias.

Releases through 0.17.0 were published as quoin/math; their notes are in [Quoin's changelog](https://github.com/One-Learning-Community/quoin/blob/main/CHANGELOG.md).
