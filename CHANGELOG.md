# Changelog

All notable changes are recorded here. The format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and the project follows [Semantic Versioning](https://semver.org/).

## [Unreleased]

### Added

- `\color` and `\textcolor` read xcolor mixing expressions over the base names: tints such as `red!50`, mixes such as `red!30!blue`, longer chains, and `-red` for the complement. Each mix is computed in xcolor's color models, so the result matches LaTeX. These values were drawn in the default color with a note.

### Changed

- The MathML writer writes an empty `<mo fence="true" stretchy="true" lspace="0" rspace="0"></mo>` on the missing side of a half-open fence, so a browser adds no space there.
- The MathML writer puts `mathcolor`, `stretchy`, `lspace` and `rspace` on a colored middle's own `mo`, where it used to wrap a plain `mo` in `mstyle`.
- The MathML writer marks a standard function name that is not a function with `data-function="false"`, writes a multi-letter italic name with `mathvariant="italic"`, and writes `movablelimits` where a name or operator differs from its default.
- The MathML reader reads a stretchy bar as `\middle` only with this library's spacing marker (`0.1667em` on both sides) or MathJax's shape, an empty `mrow` on each side. Other stretchy bars stay plain.
- The MathML reader does not treat a delimiter with `minsize` equal to `maxsize` as a fence. It stays an operator of that size and is written as `\Bigl(` and the like.
- The MathML reader reads an empty `mo` with `fence="true"` as the missing side of a fence.
- Archived MathML with a stretchy opener and no closer, written by earlier versions, reads slightly differently at the fence's inner edge.
- A plain upright word such as `\mathrm{max}`, or `Math::styled('max', 'normal')`, under a limit now stacks in every style, as TeX and browsers set it.
- A `\middle` outside `\left…\right` has a new note: "is drawn as a plain delimiter".
- `<mo>-</mo>` (a hyphen-minus) is drawn as a minus sign, also when sized with `minsize` or `maxsize` and as an accent. Firefox, WebKit and MathJax draw it so. The node keeps `-`, so MathML written back is unchanged, and TeX `-` now matches the width of the MathML it came from.
- `Math::notes()` lists a note shared by several nodes once. Distinct notes with equal content are each listed.
- The TeX writer spells a kern as a named space (`\,`, `\:`, `\;`, `\!`) or as `\mkern<n>mu`. It keeps `\hspace{…em}` at top level for an amount with at most three decimals in em, so `\hspace{-0.5em}` is unchanged, and writes `\mkern` inside scripts, where mu shrinks and em does not.
- The TeX writer writes an operator that TeX reads as ordinary (`<mo>sin</mo>`, `<mo>∂</mo>`, `<mo>mod</mo>`, a letter `<mo>`) as `\mathop{…}` between kerns where the layout's spacing differs from what the plain spelling reads back with. `<mo>lim</mo><mi>x</mi>` still writes `\lim x`.
- A stretchy accent over an operator stretches to the drawn glyph, and the operator's spacing goes around the stack once. In TeX it is written as kerns outside the accent with the base as an op atom, `\: \widehat{\mathop{x}} \:`. Widths at 18pt:
  - `<mover><mo>x</mo><mo>^</mo></mover>` goes from 27.58pt to 20.31pt, and the same operator between `a` and `b` from 46.89pt to 39.63pt.
  - `<mover><mo>=</mo><mo>^</mo></mover>` and its TeX source `\widehat{=}` go from 26.91pt to 28.04pt, since the spacing now sits around the stack.
  - The name `sin` with a hat, then `x`, goes from 44.82pt to 46.97pt, by the hat's overshoot.
  - `\underbrace` and `\overbrace` over an operator letter go from 27pt to 21.34pt, equal to their round trip.
  - TeX that reads as an accent over an operator draws the same way: `\widehat{\mathop{x}}` goes from 18.04pt to 12.31pt, and `\underbrace{=}` from 27pt to 28pt.
  - An accent with `stretchy="false"` keeps 18.62pt.
- Every table row has TeX's array strut: at least 0.84 em above the baseline and 0.36 em below, with no gap beyond it. A `matrix`, `pmatrix` or MathML `mtable` of two rows is 2.4 em tall, where it was 1.92 em, and a row with an empty cell no longer shrinks. Content taller than the strut grows its row, and the old rule that rows sit at least 1.2 em apart is gone. An alignment (`aligned`, `align`) adds TeX's 0.3 em `\jot` between rows.
- `\\[4pt]` and `\\[1em]` after a row of an environment, or of a formula with a top-level `\\`, now add that space after the row, and the "row spacing is not drawn" note no longer appears for it. It still appears for a `\\[len]` that no row gap holds: after the last row, or in an environment that has no rows to space. The TeX writer writes the space in em, `\\[0.4em]`, and reads it back to the same tree. The MathML and OMML writers do not carry it. `Grid` has a new `rowSpacing` parameter and property, in em after each row.
- Goldens, each changed on purpose:
  - The round-trip fuzz pins moved, each time by shrinking the failing set. `WELL_FORMED` went 2097, 2084, 1177, 753. `SOUP` went 658, 653, 431, and the last step added two inputs: `) \left( \\ = \| ,` and `\\ \left( \\ \textcolor{red}{=} \middle| \cdot`. A `\\` first in an unclosed fence now reads as a real fence, so they show the older line-break-in-fence mismatch, `\left( \\ = b \right)`. `FENCED_TEX` went 6890, 6160, 5909.
  - `SOUP` then went 431 to 300: 131 inputs now agree after the round trip and one appears, `\\ ( b \left( \left( & \ce{->} \%`. The `&` between the `\ce` arrow and `\%` hid it before. The same gap shows in `\left( \ce{->} \%`, already in the set. `WELL_FORMED` and `FENCED_TEX` did not move.
  - The round-trip fuzz pin `WELL_FORMED` went 753 to 738: the delimiters of `pmatrix` and `cases` inputs are taller and so wider, and 15 inputs with a `\ce{->}` arrow now fall inside its slack. All 66 inputs that moved are `pmatrix` or `cases`, and none was added.
  - The editor SVG snapshots `mathlive-table-0`, `wiris-viewer-table-0`, `-1`, `-2` and `word-mml-table-0` draw taller rows and a taller delimiter.
  - `GridTest` expects rows to stack at the strut with no 0.1 em gap, `TexReaderTest` and the `cr-spacing` corpus entry expect no row spacing note for an environment, and `TexFinalReviewTest` checks the note order with a trailing `\hline` row.
  - The editor SVG snapshots `mathlive-0`, `wiris-safexml-1` and `wiris-viewer-3`, `-5`, `-8`, `-12` and `-15` replace the narrow hyphen with the wider minus.
  - `LimitsRoundTripTest` expects `\: \mathop{x}_a \:` where it expected `\mkern4mu \mathop{x}_a \mkern4mu`. `TexWriterTest` gains an assertion that `Math::space(2/18)` writes `\mkern2mu`. `FormSpacingTexTest` is new.
  - The MathML snapshots `mathml/cases`, `tex/mathml/piecewise`, `tex/mathml/linear-system`, `tex/mathml/delim-dot` and `editors/mathml/wiris-viewer-table-2` gain the empty fence `mo` with `lspace="0" rspace="0"` on the open side of a half-open fence.
  - The `tex/mathml/operatorname-star` snapshot has `<mo movablelimits="true">`.
  - `\operatorname*{argmax}_x` is written back as itself, where it was `\underset{x}{\operatorname{argmax}}`. `TexWriterTest`, `SmallFixesTest` and `TexFinalReviewTest` expect the new spelling.
  - `\mathop{\textcolor{red}{\int}}_0^1` is written with `\displaylimits`, where it was `\limits`. `TexColorFinalTest` expects it.
  - `MathmlColorTest` expects a colored `\middle` as `<mo mathcolor="#ff0000" stretchy="true" lspace="0.1667em" rspace="0.1667em">|</mo>`, with no `mstyle` around it.
  - `TexReaderTest` expects the `\middle` outside a fence note to read "is drawn as a plain delimiter", and builds `\operatorname*{argmax}` with its movable-limits flag set.
  - `UnderOverTest` builds its limit names as function names, since a plain upright word is no limit name.

### Fixed

- `\limits`, `\nolimits`, `\displaylimits`, `\mathop`, `\operatorname*` and a colored limit name keep their limits through TeX and MathML round trips in both styles: `\sum\limits_a^b`, `\lim\limits_{x \to 0}`, `\operatorname*{argmax}_x`, `\operatorname*{E}_x`, `\mathop{\int}_0^1`, `\textcolor{red}{\lim}_x`.
- `\mathop{\mathrm{ab}}_a` moves its limits like `\mathop{\mathbf{ab}}_a` does.
- A MathML operator with one-sided or explicit spacing keeps its width when written as TeX. The TeX writer uses a class (`\mathrel`, `\mathbin`) where one fits and `\mkern` otherwise. This covers letter operators such as `<mo>x</mo>` and large operators spaced 1mu on a side.
- `\middle` reads back from MathML: `\left( a \middle| b \middle| c \right)`, bra-ket notation, and colored middles written as `\color` switches (`\color{#ff0000}\middle| b \color{#0000ff} c`).
- `x \% \middle| y` keeps its width. The text writer no longer puts a double space before a middle.
- `\big` and `\Big` delimiters keep their size through MathML and are not turned into `\left…\right`.
- Half-open fences read back from MathML: `\left[ \frac{a}{b} = c \right.` and `\left. \frac{a}{b} \right|_0`.
- `\color` and `\textcolor`, with or without a `[model]`, inside `\operatorname` and `\tag` no longer leak their spec into the name or label.
- A `\tag` label keeps symbols such as `\alpha` and escapes such as `\%`, drops formatting commands, and is always valid TeX, also when the label is unbraced.
- A limit control after a class atom or a plain word, as in `\mathrel{\sum}\limits_a` or `\mathrm{max}\limits_x`, gets a note, since TeX rejects it. The TeX writer writes it as valid TeX.
- A `\\` dropped by the TeX reader no longer spaces the operator after it: `\left( \\ = b \right)` is as wide as `\left( = b \right)`. It no longer adds a second thin space beside a fraction either: `\left( \frac{a}{b} \\ \sin x \right)` goes from 66.72pt to 63.72pt in text style, and `\left( \frac{a}{b} \\ \frac{a}{b} \right)` from 40.72pt to 37.72pt. A repeated dropped `\\` no longer reads as `{}`, and a function name before a fence loses its trailing space across an empty fallback.
- `<mo>sin</mo>`, `<mo>∂</mo>`, `<mo>mod</mo>` and a letter `<mo>`, alone, in a fence or in a root, keep their spacing through TeX. `\sin^2` read from MathML keeps its width.
- A scripted symbol operator keeps its width through TeX: `<msup><mo>∂</mo><mn>2</mn></msup>` (18.93pt, which was written back as 15.93pt) and `<msub><mo>∞</mo><mi>x</mi></msub>` (35.33pt, which was written back as 25.33pt).

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
