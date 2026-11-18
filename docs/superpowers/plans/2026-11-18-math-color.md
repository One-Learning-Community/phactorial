# Color in Formulas Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Read `\color`, `\textcolor` and `mathcolor` into a color node, draw it in SVG and PNG, hand it to layout consumers on `Glyph` and `Rule`, and write it to MathML, OMML and TeX, shipping in 0.20.

**Architecture:** A wrapper node `Node\Color` lays out its content inside a new `Box` tagged with the color. `Box`'s walk carries the current color down, and the innermost tag wins. It hands out `Glyph` and `Rule` copies with `color` set. Wrapper-aware code (`Fallback::unwrap()` and friends) sees through the node, so a colored token keeps its operator behavior. The TeX switch form makes an *inline* color that `Row` splices into its own sequence for spacing.

**Tech Stack:** PHP 8.4, PHPUnit, PHPStan, Imagick (PNG).

**Spec:** `docs/superpowers/specs/2026-11-18-math-color-design.md`. Read it before any task; it holds the value tables, the writer rules and the reasons.

## Global Constraints

- PHP `^8.4`. Gate every task on `/opt/homebrew/opt/php@8.4/bin/php -d xdebug.mode=off vendor/bin/phpunit` and `composer analyse` in `packages/core`, both clean.
- Work on local branch `math-color` off `main` (`109ba24`). Never push. Never `git stash`.
- Commits: conventional prefix, ending `Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>`, no session link.
- A color in the tree, on a `Box`, or on a display item is always `#` plus six lowercase hex digits. Nothing else reaches SVG, PNG, MathML or OMML.
- Uncolored output stays byte-identical in every writer. Any golden that changes is listed in the task's report with the reason.
- New constructors are `@internal`. New public properties and methods go in `tests/Api/api.txt` (`UPDATE_API=1 vendor/bin/phpunit --filter ApiManifestTest`) and in a new "0.20 additions" section of `docs/superpowers/specs/2026-11-18-api-audit.md`.
- Tests, docs and fixtures are generic: no logo, no product names. Docs prose follows the beautiful-prose skill: US English, no em dashes, no project name.

## Review Focus

1. **Hostile values.** `\color{red" onload="x}`, `\textcolor{url(#a)}{x}`, `mathcolor="red;fill:url(x)"`, `mathcolor="javascript:x"`. Each is rejected with a note. The SVG, MathML and OMML output contains no part of the value. TeX write-back keeps only `colorName()`'s safe characters. (Task 5 and Task 6 tests.)
2. **Colored script bases.** `\textcolor{red}{x}^2` and `\textcolor{red}{f}'` place their scripts exactly where the uncolored forms do, because the color box copies `italic`, `simple`, `firstGid`, `lastGid`, `lastAscent`, `lastDescent` and `trail`. (Task 2 test.)
3. **Empty groups.** `\textcolor{red}{}`, `{\color{red}}`, `\color{red}` at the end of a formula, and `<mstyle mathcolor="red"/>` give zero width and no exception. (Task 5 and Task 6 tests.)
4. **Delimiters and cells.** `\textcolor{red}{\left( \frac{a}{b} \right)}` colors the stretched parentheses. In `\left( a \color{red} \middle| b \right)`, the middle stretches and is red. A colored matrix cell aligns like an uncolored one. (Task 3 and Task 4 tests.)
5. **Nesting.** In `\textcolor{red}{a \textcolor{blue}{b} c}`, `b` is blue and `a` and `c` are red. SVG uses one `<g>` per run of same-colored items, not one per item. (Task 2 and Task 7 tests.)

---

### Task 1: Color values

**Files:**
- Create: `packages/core/src/Colors.php` (final, `@internal`, private constructor)
- Create: `packages/core/tests/ColorsTest.php`

**Interfaces:**
- Produces:
  - `Colors::tex(string $spec, ?string $model = null): ?string`: a normalized `#rrggbb`, or null when rejected.
  - `Colors::css(string $value): ?string`: the same, for MathML.

- [ ] **Step 1: Record the performance baseline before any code changes.** Write `bench-color.php` in the session scratchpad, not the repo. It times 200 rounds of `MathRender::fromTex($big)->svg()`, where `$big` is a 400-term sum with fractions, roots and `\left…\right` pairs. Run it on `main` and save the number for Task 9.
- [ ] **Step 2: Write the failing tests.**
  - `test_xcolor_base_names` uses a data provider, case-sensitive as xcolor is: `black #000000`, `blue #0000ff`, `brown #bf8040`, `cyan #00ffff`, `darkgray #404040`, `gray #808080`, `green #00ff00`, `lightgray #bfbfbf`, `lime #bfff00`, `magenta #ff00ff`, `olive #808000`, `orange #ff8000`, `pink #ffbfbf`, `purple #bf0040`, `red #ff0000`, `teal #008080`, `violet #800080`, `white #ffffff`, `yellow #ffff00`.
  - `test_tex_hex`: `#F00` gives `#ff0000`, and `#2350FF` gives `#2350ff`.
  - `test_tex_models`:
    - `('2350FF', 'HTML')` gives `#2350ff`.
    - `('0.14,0.31,1', 'rgb')` gives `#244fff`, each channel being `round(v*255)`, half away from zero.
    - `('35,80,255', 'RGB')` gives `#2350ff`.
    - `('0.5', 'gray')` gives `#808080`.
  - `test_tex_rejects` covers `red!50`, `-red`, `Red`, `#ff00`, `('1.2,0,0','rgb')`, `('256,0,0','RGB')`, `('x','cmyk')`, `''` and `red" onload="x`. Each returns null.
  - `test_css`: case-insensitive `#rgb`, `#rrggbb` and the sixteen HTML 4 names with their CSS values. `green` gives `#008000` and `gray` gives `#808080`. `rgb(1,2,3)`, `currentColor`, `transparent` and `orange` return null.
- [ ] **Step 3: Run `vendor/bin/phpunit --filter ColorsTest`.** Expected: FAIL, because the class is not defined.
- [ ] **Step 4: Implement `Colors` with two constant maps,** `XCOLOR` and `CSS`, plus the model arithmetic. Any value that does not match its model's exact grammar returns null.
- [ ] **Step 5: Run the tests.** Expected: PASS.
- [ ] **Step 6: Commit** `feat: color value parsing for TeX and MathML`.

### Task 2: Colored display items, box walk and the color node

**Files:**
- Modify: `packages/core/src/Layout/Glyph.php`, `src/Layout/Rule.php`, `src/Layout/Box.php`
- Create: `packages/core/src/Node/Color.php`
- Modify: `packages/core/src/Render/TextWriter.php` (pass-through, at both `TexSource` arms)
- Modify: `tests/Api/api.txt`, the API audit, `tests/Node/ChildrenTest.php`
- Create: `packages/core/tests/Layout/ColorBoxTest.php`

**Interfaces:**
- Produces:
  - `Glyph::$color` and `Rule::$color`: `?string`, the last constructor parameter, default null. `translated()` keeps it.
  - `Glyph::withColor(string $color): static` and `Rule::withColor(string $color): static`, both public.
  - `Box::$color`: `?string`, `@internal`, default null.
  - `final class Node\Color extends Node`, constructed as `new Color(Node $content, string $color, bool $inline = false)`, with `public readonly` properties `content`, `color` and `inline`. `children()` returns `[$content]`.
  - `Color::box(Box $inner, string $color): Box`, static and `@internal`: a new box that copies the inner box's metrics and kern/trail fields, places the inner box at (0, 0) and sets `color`. Tasks 3 and 4 reuse it.

- [ ] **Step 1: Write the failing tests in `ColorBoxTest`.** Build the nodes directly, for example `new Color(Math::row('a+b'), '#ff0000')`.
  - `test_items_carry_the_color`: every item of `Math::layout($colored)` has `color` `'#ff0000'`, through both `each()` and `$items`.
  - `test_uncolored_items_stay_null`
  - `test_innermost_wins`: `a`, then a nested `#0000ff` color around `b`, then `c`.
  - `test_metrics_match`: the colored box's width, ascent, descent, italic, simple and trail equal those of its content.
  - `test_script_on_colored_base`: the superscript glyph of `Scripts(Color(x), null, 2)` sits at the same (x, y) as in `Scripts(x, null, 2)`.
  - `test_rules_colored`: the fraction bar inside a color is a `Rule` with the color set.
  - `test_with_color`: returns a copy, and the original keeps null.
  - Also add a `color` case to `ChildrenTest`.
- [ ] **Step 2: Run the tests.** Expected: FAIL.
- [ ] **Step 3: Implement.** `walk($fn, $dx, $dy, ?string $color)` sets `$color = $this->color ?? $color` on entry. Each leaf goes to `$fn` as is when `$color` is null, and as `withColor($color)` otherwise. `$items` goes through the same walk. `Color::layout($c)` returns `self::box($this->content->layout($c), $this->color)`. `TextWriter` treats `Color` like `TexSource`.
- [ ] **Step 4: Update the API record.** Run `UPDATE_API=1` and review the diff. It should add only `Glyph::$color`, `Glyph::withColor()`, the same two on `Rule`, and the `Node\Color` class with its properties and `children()`. Add a row for each to the audit's new "0.20 additions" section, ruled **public**. The reason: layout consumers draw the color.
- [ ] **Step 5: Run the full suite and PHPStan.** Expected: green. No golden changes.
- [ ] **Step 6: Commit** `feat: a color node, and colored glyphs and rules in the layout`.

### Task 3: Colored tokens keep their behavior

**Files:**
- Modify: `packages/core/src/Node/Fallback.php` (`unwrap()`, `displays()`, new `color()`), `src/Node/Row.php` (line 111), `src/Node/Fenced.php` (`stretchMiddles()` and `stretchMiddle()`), `src/Math.php` (near lines 407 and 499), `src/Tex/TexReader.php` (the wrapper checks near lines 684, 1492, 1519 and 1740), `src/Mathml/MathmlReader.php` (`pair()` and `bars()` near lines 830 and 910), and the unwrap loops in `src/Render/MathmlWriter.php` (`isFunction()`, `isStandardName()`)
- Create: `packages/core/tests/Layout/ColorSpacingTest.php`

**Interfaces:**
- Consumes: `Color`, `Color::box()` (Task 2).
- Produces: `Fallback::color(Node $n): ?string`, static and `@internal`. It returns the innermost color among the wrappers `unwrap()` peels, or null.

- [ ] **Step 1: Write the failing tests,** building the trees directly from nodes.
  - `test_colored_binary_operator_keeps_spacing`: `a`, `Color(+)`, `b` has the same width as `a + b`.
  - `test_colored_operator_is_colored`: the `+` glyph of the row above has color `#ff0000`.
  - `test_colored_sum_keeps_limits`: `UnderOver(Color(∑), …)` in display style stacks its limits.
  - `test_colored_middle_stretches`: `Fenced('(', ')', Row[a, Color(Middle('|')), b])` gives the middle the same glyph height as the uncolored one, and gives it the color.
  - `test_colored_cell_aligns`: a matrix with a colored cell has the same cell x positions as an uncolored one.
  - The MathML reader's `pair()` and `bars()` change here, but their test lives in Task 6, which is where `mathcolor` is first read.
- [ ] **Step 2: Run the tests.** Expected: FAIL.
- [ ] **Step 3: Implement.** Add `Color` everywhere `DisplayStyle` and `TexSource` are seen through, and rebuild it in `Fenced`'s two `stretchMiddle` functions. In `Row::build()`, when `$seen` is an `Operator` laid out by `layoutAs()`, wrap the result with `Color::box()` if `Fallback::color($child)` is not null.
- [ ] **Step 4: Run the full suite and PHPStan.** Expected: green, and no goldens change.
- [ ] **Step 5: Commit** `feat: a colored token keeps its operator behavior`.

### Task 4: The inline color splices into its row

**Files:**
- Modify: `packages/core/src/Node/Row.php`, `src/Node/Fenced.php` (`middleRow()` and the `$reuse` indices)
- Modify: `packages/core/tests/Layout/ColorSpacingTest.php`

**Interfaces:**
- Consumes: `Color::$inline`, `Color::box()`, `Fallback::color()`.
- Produces: `Row::items(): list<array{Node, ?string}>`, `@internal`. It returns the children, with each inline color child spliced in. A child counts as an inline color when its content, after peeling `TexSource` and contentful `Fallback`, is a `Row`; the splice is recursive. Each entry pairs a node with the innermost inline color around it. `forms()`, `build()`'s loop, and the `$reuse` and `$boxes` indices all index into `items()`. `Fenced` uses the same indices.

- [ ] **Step 1: Write the failing tests.**
  - `test_switch_form_keeps_binary_spacing`: `Row[a, TexSource(Color(Row[+, b], '#ff0000', inline: true), '{\color{red} ', '}')]` has the same width as `a + b`, and `+` and `b` are red.
  - `test_switch_middle_stretches`: `Fenced('(', ')', Row[a, Color(Row[Middle('|'), b], inline: true)])` stretches the middle and colors it.
  - `test_non_inline_row_stays_one_atom`: `Row[a, Color(Row[+, b])]` keeps today's width, the one `a {+ b}` has.
  - `test_nested_inline`: an inline color inside an inline color splices both, and the inner color wins.
- [ ] **Step 2: Run the tests.** Expected: FAIL.
- [ ] **Step 3: Implement `items()`.** `build()` wraps each item's box with `Color::box()` when the item's color is not null. This wrap goes outside the operator wrap from Task 3, so the inner color still wins.
- [ ] **Step 4: Run the full suite and PHPStan.** Expected: green, and no goldens change.
- [ ] **Step 5: Commit** `feat: a color switch does not break the row's spacing`.

### Task 5: TeX reading and writing

**Files:**
- Modify: `packages/core/src/Tex/TexReader.php` (the switch form at line 406, the command forms at lines 1348 and 1352, `colorName()` at line 1964, and new `colorArg()`), `src/Node/TexSource.php` (docblock: it now also keeps the spelling of a drawn construct), `src/Render/TexWriter.php`
- Create: `packages/core/tests/Tex/TexColorTest.php`
- Modify: any existing test that asserts "color is not drawn"

**Interfaces:**
- Consumes: `Colors::tex()`, `Color`.
- Produces: the reader's trees, `TexSource(Color(…))`, with the following shapes.
  - The switch form gives `TexSource(Color(row, hex, inline: true), '{\color{SPEC} ', '}')`.
  - `\textcolor` gives `TexSource(Color(arg, hex), '\textcolor{SPEC}{', '}')`.
  - The command form with no content gives `TexSource(Row([]), '\color{SPEC}', '')`, as today but without the note.
  - SPEC is the spelling as read, with an optional `[model]` kept: `\color[HTML]{2350FF}`.
  - A rejected value keeps today's `TexSource` and filtered spelling. The content is wrapped in a `Fallback` with an `Approximated` note, "unknown color `SPEC`; drawn in the default color", made once per distinct SPEC.

- [ ] **Step 1: Write the failing tests.**
  - Each accepted form reads to the shape above, with the hex value from Task 1: names, `#hex`, `[HTML]`, `[rgb]`, `[RGB]` and `[gray]`, for both `\color` and `\textcolor`.
  - No note is raised for an accepted value.
  - TeX round trips are byte-identical for `\textcolor{red}{x}`, `a {\color{blue} b + c} d`, `\color[HTML]{2350FF} x`, `\textcolor{red!50}{x}` (rejected, still round-trips) and `\color{red}` at the end of a group.
  - `TexWriter` writes a `Color` with no `TexSource` (built directly) as `\textcolor{#ff0000}{x}`.
  - The hostile values from Review Focus 1 produce the note. Re-reading the written TeX gives the same tree.
  - The empty groups from Review Focus 3 lay out to width 0.
- [ ] **Step 2: Run the tests.** Expected: FAIL.
- [ ] **Step 3: Implement.** `colorArg()` reads an optional `[model]` (letters only) and then the group. It returns the spelling, plus the hex from `Colors::tex()` or null when rejected.
- [ ] **Step 4: Run the full suite and PHPStan.** Expected: green. List each golden or corpus expectation that changed, with the reason: the color is now drawn, or the note is gone.
- [ ] **Step 5: Commit** `feat: read and write TeX color`.

### Task 6: MathML reading and writing

**Files:**
- Modify: `packages/core/src/Mathml/MathmlReader.php` (`dispatch()`: wrap any element that has `mathcolor`), `src/Render/MathmlWriter.php`
- Create: `packages/core/tests/Mathml/MathmlColorTest.php`

**Interfaces:**
- Consumes: `Colors::css()`, `Color`.
- Produces: `mathcolor` on any element reads to `Color(node, hex)`, non-inline. A rejected value is wrapped in a `Fallback` with the same note text as Task 5, once per value, around the uncolored node. The writer turns a non-inline `Color` into `<mstyle mathcolor="#rrggbb">…</mstyle>`. It turns an inline `Color` into one such `mstyle` around each spliced item, written in place in the parent `mrow`. (MathML Core treats an `mstyle` holding one embellished operator as that operator, so forms survive.)

- [ ] **Step 1: Write the failing tests.**
  - `mathcolor` on an `mi`, an `mrow` and an `mstyle`, with nested colors, reads to the right tree, and the innermost color wins.
  - A colored `<mo>(</mo>` still pairs as a fence (moved here from Task 3).
  - Writer output matches exactly for both color kinds.
  - Round trips:
    - TeX `a \textcolor{red}{b}` through MathML and back to TeX gives `a\textcolor{#ff0000}{b}`, or the writer's exact equivalent spacing. Pin the exact string.
    - TeX `a {\color{red} + b}` through MathML and back has the same layout width and the same colors.
  - The hostile values from Review Focus 1 are rejected, and the written MathML holds none of their text.
  - `<mstyle mathcolor="red"/>` gives no exception and zero width.
- [ ] **Step 2: Run the tests.** Expected: FAIL.
- [ ] **Step 3: Implement.**
- [ ] **Step 4: Run the full suite and PHPStan.** Expected: green. Report any golden changes; editor fixtures that use `mathcolor` may change.
- [ ] **Step 5: Commit** `feat: read and write MathML mathcolor`.

### Task 7: SVG and PNG

**Files:**
- Modify: `packages/core/src/Render/SvgRenderer.php` (`render()` and `glyphPaths()`), `src/Render/PngRenderer.php` (`render()`)
- Modify: `packages/core/tests/Render/SvgRendererTest.php`, `tests/Render/PngRendererTest.php`

**Interfaces:**
- Consumes: `Glyph::$color` and `Rule::$color`.

- [ ] **Step 1: Write the failing tests.**
  - The SVG for `a\textcolor{red}{bc}d` has exactly one `<g fill="#ff0000">` holding two paths, and `a` and `d` sit directly in the root group.
  - A colored `\frac` puts its `<rect>` inside the colored group.
  - `\textcolor{red}{\left(x\right)}` puts its delimiter paths inside the colored group.
  - Nested colors give red, then blue, then red as three sibling groups.
  - The output for an uncolored formula is byte-identical to `main`'s; the existing snapshots cover this.
  - `glyphPaths()` groups colored glyphs the same way.
  - In the PNG for `\textcolor{#ff0000}{\frac{a}{b}}`, the sampled pixels at the middle of the bar are red and the uncolored glyph pixels are the renderer's color.
- [ ] **Step 2: Run the tests.** Expected: FAIL.
- [ ] **Step 3: Implement.** SVG opens a `<g fill>` when the color changes from null, closes it on a change back, and swaps it on a change to another color. PNG calls `setFillColor()` only when the color changes, and restores the renderer's color on null. Opacity stays as it is.
- [ ] **Step 4: Run the full suite and PHPStan.** Expected: green. Snapshot goldens change only for fixtures that contain color; list them.
- [ ] **Step 5: Commit** `feat: SVG and PNG draw color`.

### Task 8: OMML

**Files:**
- Modify: `packages/core/src/Render/OmmlWriter.php`
- Modify: `packages/core/tests/Render/OmmlWriterTest.php`

**Interfaces:**
- Consumes: `Color`, `RunFormat`.
- Internal change: `node()` and its helpers carry `?RunFormat $f` instead of the rendered `$rPr` string. `run()` renders `rPr($f)` itself. A `Color` node recurses with `new RunFormat(strtoupper(substr($color, 1)), $f?->sizeHalfPoints, $f?->shadingHex)`. `ctrlPr($f)` returns `'<m:ctrlPr>' . rPr($f) . '</m:ctrlPr>'` when `$f?->colorHex !== null`, and `''` otherwise. That covers the equation-wide color too (Claude's call: Word should draw bars in the run color whichever way it was set).

- [ ] **Step 1: Write the failing tests.**
  - The runs of `\textcolor{red}{x}` carry `<w:color w:val="FF0000"/>`, and runs outside carry none, or the `RunFormat` color.
  - A colored `\frac` writes `<m:fPr><m:ctrlPr>…FF0000…</m:ctrlPr></m:fPr>`, and the same holds for `m:radPr`, `m:dPr`, `m:naryPr`, and the accent, bar, groupChr, script, limit, func, eqArr and matrix property elements the writer emits. `ctrlPr` is the last child of each property element, as the OMML schema requires.
  - An uncolored formula with no `RunFormat` color is byte-identical to `main`.
- [ ] **Step 2: Run the tests.** Expected: FAIL.
- [ ] **Step 3: Implement.** Where an object writes no property element today (a barred `m:f`), write one when `ctrlPr` is not empty. If some piece can't be colored in OMML, list it for the docs in Task 9.
- [ ] **Step 4: Run the full suite and PHPStan.** Expected: green. OMML goldens that use a `RunFormat` color gain `ctrlPr`; list them.
- [ ] **Step 5: Commit** `feat: Word equations draw color`.

### Task 9: Performance, docs and release notes

**Files:**
- Modify: `site/src/README.md` (near line 50), `site/src/input.md` (near lines 81, 83 and 164), `site/src/backends.md` (`Glyph::$color` and `Rule::$color` for layout consumers), `CHANGELOG.md` (`[Unreleased]`)

- [ ] **Step 1: Rerun the Task 1 benchmark on the branch.** Add a second run with the whole formula wrapped in `\textcolor{red}{…}`. Report all three numbers. Expected: the uncolored run is within noise of the baseline (±3%).
- [ ] **Step 2: Update the docs.**
  - Remove "color is not drawn".
  - List the accepted values from the spec, and say that a rejected value is drawn in the default color with a note.
  - Say that `mathcolor` is read and `mathbackground` and `mathsize` are still ignored.
  - Add the theming sentence: uncolored ink follows the renderer's color, while an explicit color is fixed and does not follow a dark theme.
  - Add any OMML gaps from Task 8.
- [ ] **Step 3: Update the changelog.** List the feature, the new public members, and the switch-form spacing fix. List each golden that changed, with the reason.
- [ ] **Step 4: Check the logo formula without committing it.** In the scratchpad, render `\mathrm{Ph}\textcolor{#2350ff}{!}` to SVG. Confirm the `!` sits in a colored group with the same spacing as `\mathrm{Ph}!`. Don't edit `wordmark.php`; that is the maintainer's call.
- [ ] **Step 5: Run the full core and commonmark suites on PHP 8.4 with Xdebug off, plus PHPStan for both packages.** Expected: green.
- [ ] **Step 6: Commit** `docs: color in formulas`.
