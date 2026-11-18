# Changelog

All notable changes are recorded here. The format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and the project follows [Semantic Versioning](https://semver.org/).

## [Unreleased]

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
