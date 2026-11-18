# Stability

Both packages follow [Semantic Versioning](https://semver.org/). From 1.0, a minor release adds and a major release breaks. This page lists what each kind of release covers. Before 1.0, any release may break; the [changelog](https://github.com/One-Learning-Community/phactorial/blob/main/CHANGELOG.md) says where.

The public API is every class, method, property and constant that is not marked `@internal`. Each package keeps a manifest of it in `tests/Api/api.txt`, and a test fails when the code and the manifest disagree. A change to a manifest line is a change to the API.

## What a minor release may add

- Node types, builder methods, optional parameters, config values and config keys.
- Supported TeX commands and MathML elements. A command that was unknown becomes drawn, and its note disappears. The changelog lists each newly supported command.
- Raising an input limit.
- Better output. Spacing, kerning, glyph choice, stretch sizes, rule thickness and every coordinate may shift.

## What breaks

A change to any of these is a breaking change, and it waits for a major release.

- Removing or renaming a class, method, property, constant or enum case.
- Renaming a parameter or changing its default. PHP's named arguments make `Math::layout(font: $f)` a call to a parameter name, so the names freeze with the method.
- Changing a return type, even to a wider one. `Math::frac()` returns `Fraction`, and it will keep returning exactly that.
- Changing what a documented value means.
- Adding a case to `NoteLevel`.
- Lowering an input limit (`TexReader::MAX_*`, `MathmlReader::MAX_*`).
- Dropping support for a TeX command or MathML element, changing what a supported command means, or making a supported construct produce a `Dropped` note.

Anything marked `@internal` is outside this page. That covers the constructors of result, note, exception, display-item and node classes, and engine detail such as `Grid`'s constants. Build formulas through `Math`, `MathRender` and the readers.

## The node tree

A formula is a tree of `Phactorial\Node` objects. The readers produce it, `MathRender::node()` returns it, and the writers consume it. Reading it is supported; building it by hand is not.

- Read the tree through its public read-only properties and `children()`. `children()` returns every child in reading order and promises nothing about the count.
- Build the tree through `Math`. Node constructors are internal, because each one holds an invariant that `Math` checks.
- `Node` is sealed. Do not extend it. The writers handle every node type the library ships, and each of the four throws `InvalidArgumentException`, naming the class, for one it does not know.
- A minor release may add a node type. Give every `match` or `instanceof` chain over nodes a default branch. A walker that reaches an unknown node recurses through `children()`, or hands it to `TextWriter::write()`.
- The builders return the exact class they declare. `Math::sup()` returns `Scripts`.
- The shape the readers give a documented construct is stable at the level of class and property: `\frac` reads as a `Fraction`. The wrapper nodes the readers place around it (`TexSource`, `DisplayStyle`, `Fallback`) and the nesting of `Row` inside `Row` are not.

## The display list

`Math::layout()` returns a `Box`, and the SVG and PNG writers walk it. `Box::$items` and `Font`'s metrics cannot be assigned. Treat a box's other fields as read-only. The constructors of `Glyph`, `Rule` and `Box` are internal: build boxes with `Math::layout()`.

`DisplayItem` is sealed. In every 1.x release, `Box::each()` and `Box::$items` yield only `Glyph` and `Rule`. A new kind of item would arrive behind an optional `Math::layout()` parameter that names the kinds the caller can draw. A backend written against 1.0 never meets an item it cannot draw.

The conventions are fixed: units are points, y points down, the origin is the left end of the baseline, a `Glyph`'s `x` and `y` are its origin on the baseline, `sx` and `sy` scale about that origin, and a `Rule`'s `x` and `y` are its top-left corner.

## Output

Each writer's output is stable at the level a program can observe.

| Output | Fixed | Free to change |
|---|---|---|
| `MathRender::svg()` | The root's `role`, `aria-label`, `aria-hidden` and inline `vertical-align`, with their meaning; `width`, `height` and `viewBox`; CSS pixels as the unit; `currentColor` as the default color. | The markup below the root: `<g>`, `<path>`, transforms and how many paths. The text of the `aria-label`. |
| `SvgRenderer` | `render()` keeps `width`, `height`, `viewBox`, the units and the default color `#000000`, and writes no ARIA attributes. `glyphPaths()` keeps its view box of `0 -ascent width height` and `overflow="visible"`. | Path data and element order. |
| MathML | The namespace, the `display` attribute, the element chosen for a construct (`\frac` is `<mfrac>`) and the attributes that carry meaning, such as `mathvariant`, `stretchy` and `accent`. | Redundant wrappers such as an `<mrow>` around one child. |
| OMML | The element chosen for a construct, the run properties `RunFormat` sets, and the alignment values. | Element order inside a run. |
| TeX | Reading the output back gives an equivalent tree. | The exact characters and spacing. |
| Text | `text()` stays a plain string that a screen reader can speak. | The characters chosen for a construct. |
| PNG | A transparent background, the pixel-size formula in [Output](./output.md#png), and the fields of `MathPng`. | The pixels. |
| Notes | The `element` values, and the level a construct gets, within the coverage rules below. | The `reason` text. |

The SVG carries no class names and will not, because a class name becomes API the day someone styles it.

Golden output may change in any minor release. The changelog lists each such change. A program that needs byte-stable output pins a patch range, such as `~1.2.0`.

### Defaults

Two layers have two sets of defaults, and the split is a rule.

- `MathRender` serves web pages: inline style, 16 pixels, `currentColor`.
- Every lower layer follows its own format. `Math::layout()` and `MathmlWriter::write()` default to display style, `Math::layout()` to 12 points, the renderers to black, and `OmmlWriter::oMathPara()` to center.

A default does not change in a minor release.

### Sizes

`Math::BIG_SIZES` promises four sizes, numbered 1 to 4, each larger than the one before. It does not promise their heights.

## Coverage

The readers' handling of input they do not support is fixed. Their coverage grows.

- The readers never throw, on any input.
- Each unsupported construct produces a note. The level follows one rule: `Dropped` when content is lost, `Approximated` when it is drawn differently.
- The input limits, and what each does when reached, are documented under each reader. Most truncate and note; at the depth limit, deeper TeX groups are drawn as their source and deeper MathML is flattened to text.
- A note may move from `Dropped` to `Approximated` as support improves.

If a supported command ever has to go, it is deprecated first. It keeps working, with an `Approximated` note that names its replacement, and it leaves only in a major release.

## The CommonMark extension

In `phactorial/commonmark`, these are the contract:

- the `math` config keys and values;
- the HTML shapes and classes: `span.math.math-inline`, `span.math.math-display`, `div.math.math-display`, and the error forms `code.math.math-error` and `div.math.math-display.math-error > code`. What sits inside the wrapper, `<svg>` or `<math>`, follows the output rules above;
- a fence whose first info word is `math` is typeset, and every other fence falls through to the next renderer;
- a paragraph that is only `$$…$$` becomes a display block, and math inside link text or image alt text returns to its source text;
- the priorities: the inline parser at 50, the `FencedCode` renderer at 50, and the `MathInline` and `MathBlock` renderers at 0;
- the read-only node properties `MathInline::$tex`, `$display` and `$source`, and `MathBlock::$tex` and `$source`.

The parser, the renderers, the listener and the output helper are internal.
