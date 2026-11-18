# Color in formulas: design

Status: draft for approval, 2026-11-18. Target release: 0.20.

## Why

A formula author who writes `\color{blue}` or `mathcolor="blue"` today gets black ink and an `Approximated` note that says "color is not drawn". The demand is concrete: the project's own logo is a formula with one colored glyph, and the only way to draw it was to edit the SVG by hand after rendering. When this ships, the logo script renders that formula from TeX alone.

## Goals

- A color node in the tree, read from TeX `\color` (switch to the end of the group), TeX `\textcolor{c}{x}`, and MathML `mathcolor` on any element, `<mstyle>` included. The innermost color wins.
- The color reaches the public display items. `Glyph` and `Rule` gain a nullable `color`, so a backend that draws from `Math::layout()` (the PDF library downstream does, through `Box::each()`) can paint it without any other change.
- Every writer handles it. SVG and PNG draw glyphs and rules (fraction bars, radical overbars, stretchy delimiter pieces) in the color. MathML writes `mathcolor`. OMML gives each run inside the color its own `w:color`. TeX writes it back. Text ignores it.
- Only a closed whitelist of values ever reaches a writer, because color values land in SVG and XML attributes.

## Non-goals

- `\colorbox`, `\fcolorbox`, `mathbackground`, `\boxed`, `mathsize`. Background and box drawing are a separate gap with no demand yet.
- A public builder such as `Math::color()`. No caller has asked for one; readers are the only source of the node for now. Easy to add later.
- Theme-aware colors. A fixed hex cannot follow a dark theme; that is documented, not solved (see Theming).
- An OMML reader. None exists, so the OMML path is checked on the writer side only.

## Anti-goals

- Any attacker-shaped string in an attribute. A value not on the whitelist never reaches a drawing writer (SVG, PNG, MathML, OMML). TeX write-back keeps today's behavior: a value made only of safe characters (letters, digits, `#.,!:-` and space, the space being needed for `[rgb]{1, 0, 0}`) is written back unchanged through `TexSource`, so `\color{red!50}` still round-trips as the docs promise; a value that the character filter or the markup check would change is written as `{}`, never as a changed color. (Call made after review.)
- A slowdown for formulas without color. The extra cost belongs to colored groups only.
- A break for existing layout consumers. A backend that ignores the new `color` field draws exactly what it draws today.

## Approaches

Three ways were weighed for getting color from the tree onto what is painted.

- **A. The color rides on the box (chosen).** The color node lays out its content and places that box inside a new box tagged with the color. The walk in `Box::each()` and `Box::$items` carries the current color down; a tagged box replaces it for everything beneath, so the innermost color wins. Items under a color are handed out as copies with `color` set. Layout code that makes glyphs (runs, stretchers, radicals, fences, fractions) is untouched, and stretchy pieces and rules inside a colored group take the color for free. Cost: `each()` and `$items` make one copy per colored item per walk; uncolored items are handed out as today. The renderers use an internal walk that passes the resolved color beside the uncopied item, so drawing copies nothing.
- **B. Color set when each item is made.** The layout context carries the current color and every place that creates a `Glyph` or `Rule` passes it in. No copies, but roughly ten creation sites change, and every future one must remember to pass the color.
- **C. A color attribute on every node.** Spreads across all node types, and both TeX and MathML treat color as a scope rather than a token property. Poor fit for the interchange tree.

The maintainer picked A.

## Design

### Accepted values and normalization

A color is stored in the tree as six lowercase hex digits with a leading `#`, for example `#2350ff`. The reader normalizes every accepted spelling to that form.

TeX accepts:

- The nineteen base colors of the xcolor package, with xcolor's values (not CSS's, which differ for `green`, `gray` and others): black, blue, brown, cyan, darkgray, gray, green, lightgray, lime, magenta, olive, orange, pink, purple, red, teal, violet, white, yellow.
- `#rgb` and `#rrggbb`, as KaTeX and MathJax accept them.
- xcolor's model syntax for the four models that convert to hex by arithmetic: `[HTML]{2350FF}`, `[rgb]{0.14,0.31,1}`, `[RGB]{35,80,255}`, `[gray]{0.5}`. This is the form real LaTeX documents use for a hex color, since `#` is not valid there. (Call made in brainstorming: cheap to read, common in pasted source.)

MathML `mathcolor` accepts `#rgb`, `#rrggbb`, and the sixteen HTML 4 color names with their CSS values (black, silver, gray, white, maroon, red, purple, fuchsia, green, lime, olive, yellow, navy, blue, teal, aqua). Case-insensitive.

Anything else (xcolor mixing such as `red!50`, other models, CSS functions, unknown names) earns an `Approximated` note naming the value, for example "unknown color `red!50`; drawn in the default color". The content is kept and drawn uncolored; no color node is made.

### The node

`Phactorial\Node\Color`, final, a wrapper like `DisplayStyle`: `public readonly Node $content`, `public readonly string $color` (the normalized hex). Constructor `@internal`, like every node. `children()` returns the content.

`layout()` lays out the content, then returns a new `Box` with the content's metrics and fields copied through (width, ascent, descent, italic, simple, the kerning and trail fields), the content placed at (0, 0), and the color tag set. The copied fields matter: script placement and MathKern read them, and a colored base must place its scripts exactly as an uncolored one.

Several places look through wrapper nodes by type today (`DisplayStyle`, `TexSource`, `Fallback`): `Node/Fenced.php` (stretching `\middle` inside a fence), `Node/Fallback.php`, `Math.php` (table cells and the node check near line 499), `Tex/TexReader.php` (the helpers near lines 684, 1492, 1519 and 1740), and the unwrap loops in `MathmlWriter`, `TextWriter` and `OmmlWriter`. Each must treat the color node the same way, rebuilding it around the transformed content where the others are rebuilt. A colored `\middle|` must still stretch, and a colored cell must still align.

A colored token behaves exactly like the same token uncolored: spacing class, operator form, large-operator limits, stretchiness, `\middle`, and MathML fence pairing. That is what `\textcolor{red}{+}` does today, through `Fallback::unwrap()`, and it must not regress. So `unwrap()` and the MathML reader's `pair()` and `bars()` see through the color node. Where layout then works on the unwrapped node directly (`Node/Row.php` near line 111 calling `layoutAs()`, `UnderOver::operator()`, `Forms`), the unwrap also returns the innermost color, and that site wraps the box it builds in a colored box. This is one wrap per site, not a change to how glyphs are made, so approach A still holds. (Call made after review.)

### The switch form keeps spacing

Today `a \color{red} + b` loses the space around `+`, because everything after the switch becomes a nested row, which lays out as one ordinary atom. TeX and KaTeX keep the space: a color switch is not an atom boundary. This release fixes it, so the tree shape is right the first time and no later release has to change it.

The color node carries `public readonly bool $inline`. The TeX switch form (`\color` without braces around its argument's scope) makes an inline color node; `\textcolor` and MathML make a non-inline one. When a `Row` lays out its children, an inline color child whose content is a row is spliced in: its items join the parent's sequence for spacing and operator form, as if the switch were not there, and each item's box is wrapped in the color. A non-inline color node is one atom, which matches `\textcolor` and an `mrow` in MathML. The TeX writer is unchanged (the `TexSource` keeps the switch spelling).

MathML has no inline grouping: an `<mstyle>` around `+ b` makes `+` a prefix operator in a browser. So the MathML writer puts `mathcolor` on each top-level child of an inline color node, wrapping a child in `<mstyle mathcolor>` only when it is not a single element that takes the attribute itself. Reading that back gives per-item colors with the same spacing, and TeX written from it uses `\textcolor` per item. The color and the spacing round-trip; the spelling does not, and need not.

(Claude's recommendation, given when the maintainer asked; the maintainer confirms it by approving this spec.)

### The box and the walk

`Box` gains `public ?string $color = null`, marked `@internal` (engine detail, set only by the color node). The private walk gains a current-color argument. When it enters a tagged box, that box's color becomes the current one. Each leaf item is handed to the visitor as is when the current color is null, and as a copy with `color` set otherwise. `$items` resolves color the same way. The visitor signature of `each()` does not change.

Box keeps its existing contract: a placed box is never changed, so the tag is set before the box is placed.

### Display items

`Glyph` and `Rule` gain `public ?string $color = null` as their last constructor parameter. `translated()` keeps it. Each gains `withColor(string $color): static`, returning a copy. The color, when set, is always the normalized `#rrggbb`. These are public additions: the docblocks, `tests/Api/api.txt`, and a new row in the API audit (`docs/superpowers/specs/2026-11-18-api-audit.md`) record them. `DisplayItem` itself does not change.

### Writers

- **SVG.** The root `<g fill>` keeps the renderer's default color (often `currentColor`). Runs of consecutive items that share a color go inside one nested `<g fill="#rrggbb">`; uncolored items stay directly in the root group and inherit its fill. Opacity stays on the root group and applies to colored ink too. `glyphPaths()` does the same for the glyphs it is given.
- **PNG.** The fill color changes when an item's color differs from the previous one; uncolored items use the renderer's color. Opacity applies to all ink as today.
- **MathML.** A color node becomes `<mstyle mathcolor="#rrggbb">` around its content. Where the content is a single token element, the attribute goes on the token instead (`<mi mathcolor="…">`), which is shorter and equivalent. (Builder's call whether to do the token shortcut; the mstyle form is always correct.)
- **OMML.** Word should draw what SVG draws, as closely as OMML allows (maintainer's ruling). The writer already passes run properties down the tree. Under a color node, each run's properties carry `<w:color w:val="RRGGBB"/>` (uppercase, no `#`, as Word writes it), replacing the equation-wide `RunFormat` color for those runs only. Word draws structure (fraction bars, radical signs, `m:d` delimiters, n-ary signs, accents, bars, group characters) from each object's control properties, `m:ctrlPr`, which the writer does not write today. Under a color, every such object gets an `m:ctrlPr` carrying the same `w:rPr`, so a colored fraction has a colored bar. Runs and objects outside keep the `RunFormat` color, or none. Where OMML cannot color a piece SVG colors, the builder lists it in the plan and the docs.
- **TeX.** Source read from TeX keeps its spelling through the existing `TexSource` wrapper, which now wraps a color node instead of a note: `\color{red}` comes back as `\color{red}`, `[HTML]{2350FF}` as written. A color node with no `TexSource` (read from MathML) is written as `\textcolor{#rrggbb}{…}`, the form KaTeX and MathJax both read. The `TexSource` docblock, which says it is for constructs that are not drawn, is updated.
- **Text.** Writes the content and ignores the color.

### Notes

`\color` and `\textcolor` with an accepted value no longer earn a note. A rejected value earns its own `Approximated` note (above), once per distinct value. `mathcolor` with an accepted value earns nothing; a rejected one earns the same note. `mathbackground` and `mathsize` keep their current handling.

### Theming

Uncolored ink keeps following the renderer's color, so an SVG on `currentColor` still follows the page's theme. Explicit colors are fixed hex and do not adapt to a dark background. The docs say so in one sentence. Nothing more is built.

### Round trips

Fixtures, with no logo in them:

- TeX → tree → TeX keeps the color in its original spelling, for each accepted form.
- TeX → tree → MathML → tree → TeX keeps the color value (spelling becomes `\textcolor{#rrggbb}`).
- MathML `mathcolor` on a token, on an `mrow`, and on an `mstyle`, nested so the innermost wins, reads to the right tree.
- TeX → tree → OMML gives the colored runs `w:color` and leaves the rest alone.
- SVG output for a colored glyph, a colored fraction bar, a colored stretchy delimiter, and nested colors; a PNG check that colored pixels appear.
- Spacing parity: `a \color{red} + b`, `a \textcolor{red}{+} b` and `a + b` lay out to the same width; a colored `\sum` keeps its limits; a colored `\middle|` stretches; `<mo mathcolor="red">(</mo>` pairs as a fence.
- OMML: a colored fraction, radical, delimiter pair and n-ary sum each carry `m:ctrlPr` with the color.
- A layout test that `Box::each()` and `$items` hand out the right `color` on each item, and that the metrics of a colored box equal those of its content.

### Performance check

Before and after timings of layout plus SVG for a large uncolored formula and a large fully colored one. The uncolored number should not move beyond noise.

## Docs and housekeeping

- `site/src/README.md` and `site/src/input.md`: remove "color is not drawn", list the accepted values, note that `mathcolor` is now read, add the theming sentence.
- `CHANGELOG.md`: the feature, the new public fields, and any golden that changed and why.
- `site/bin/wordmark.php` can then render the logo from TeX alone. Whether to switch the site's logo over is a separate decision for the maintainer.
- Docs prose: US English, no em dashes, no project name in prose.

## Left to the builder

- The exact text of the rejected-color note.
- Whether MathML writes the token shortcut.
- Test file layout and fixture names.
