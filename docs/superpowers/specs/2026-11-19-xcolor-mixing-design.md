# xcolor mixing: design

Status: draft for approval, 2026-11-19. Size: a small change.

## Why

The 0.20 color work left xcolor color expressions out, and the docs name `red!50` as the example of a value that is drawn black with a note. This closes that loop. No report asked for it; mixing is the most common xcolor syntax past a bare name (`blue!20` for a tint, `red!50!black` for a shade), so it is the lowest bar for everyday LaTeX.

## Goals

- `\color` and `\textcolor` accept xcolor mixing expressions over the 19 base names and draw them in the color LaTeX would.
- The forms: a tint `red!50` (mixed with white), a mix `red!30!blue`, a chain `red!30!blue!60!green`, a chain ending in a tint `red!30!blue!60`, and the complement prefix `-red`, `-red!50!blue`.

## Non-goals

- `\definecolor` and `\colorlet`. Definitions live in a document's preamble, not in a formula.
- The dvipsnames, svgnames and x11names name sets. LaTeX needs a package option for them.
- Extended expressions (`rgb:red,1;blue,2`) and color series (`red!!+`, `!![n]`).
- Mixing inside a model form (`[rgb]{…}!50` is not xcolor syntax) and hex operands (`#ff0000!50`).
- An optional dependency for the arithmetic. It is hand-rolled (maintainer's call), so a formula draws the same everywhere.

## Anti-goals

- A color that differs from what LaTeX draws for the same expression.
- Any change to values accepted today, to the MathML side, or to the note for values still rejected.

## Approaches

- **Hand-rolled arithmetic in `Colors::tex()` (chosen).** A parser for the expression grammar and xcolor's mixing math, in the one function every TeX color already passes through.
- **tomloprod/colority as an optional dependency.** Suggested earlier; dropped because output would then depend on what is installed, and the math is a few lines.
- **A required dependency.** Rejected for the same reason the core has none: a few lines of arithmetic do not earn one.

## Design

**Seam.** `Colors::tex($spec, null)` today returns a base name or hex, else null. It gains a third branch: when `$spec` matches the expression grammar, it returns the mixed color as `#rrggbb`. Nothing else in the reader or the writers changes. The TeX writer already keeps the source spelling through `TexSource`, so `\color{red!50}` round-trips as written; read from MathML, the color comes back as `\textcolor{#ff8080}{…}`.

**Grammar.** `-`* *name* ( `!` *pct* `!` *name* )* ( `!` *pct* )?, with no spaces. A *name* is one of the 19 base names, case-sensitive. A *pct* is a decimal number such as `50` or `12.5`. Anything else stays rejected with today's note.

**Math, following xcolor.sty.**
- Each base name keeps the model xcolor defines it in (rgb, cmyk or gray). The builder reads these from xcolor.sty and records them beside the existing table; cyan, magenta and yellow are expected to be cmyk, and black, white and the grays to be gray.
- Start with the first name in its own model. Each `!p!name` step gives p% of the color so far plus (100 − p)% of the next name, converted into the first color's model. A trailing `!p` mixes with white the same way.
- An odd number of leading `-` complements the result of the whole expression, using xcolor's complement for that model.
- Convert to rgb with xcolor's formulas, then round each channel to 8 bits once at the end (below).
- One exception, from xcolor: when the color so far is gray and the next name is not, the color so far is first converted into the next name's model, and the mix happens there.
- xcolor documents percentages as 0 to 100 and computes nonsense outside that range without an error. A percentage above 100 is rejected with today's note. (Call made while planning, after reading xcolor.dtx.)
- Rounding: each channel is kept to 5 decimals, as TeX's dimension registers do, then turned into 8 bits as floor(255x + 0.5), xcolor's rule. Exact integer arithmetic, so ties such as 0.3 × 255 = 76.5 land the same way on every platform.

**Reference values.** No LaTeX is installed locally, so expected values come from xcolor's manual and source. Anchors to start from: `red!50` → `#ff8080`, `blue!20` → `#ccccff`, `red!50!black` → `#800000`, `red!30!blue` → `#4d00b3`, `-red` → `#00ffff`. Each cmyk and gray name appears in at least one test, both as the first color and later in a chain.

**Cost and bounds.** Linear in the expression length. Inputs are trusted per the project's trust model; no extra limits.

## Tests and docs

- `ColorsTest`: a provider of expressions and expected hex, covering every form above, the cmyk and gray names, and rejected near-misses (`red!`, `red!!50`, `red!50!`, `Red!50`, `red!50!#fff`).
- `TexColorTest`: `\color{red!50}` and `\textcolor{blue!20!black}{x}` draw in the mixed color with no note, write back unchanged to TeX, and come back from MathML as hex.
- `site/src/input.md`: add mixing to the accepted list and pick a new example for the rejected-value sentence (`ForestGreen` or `rgb:red,1;blue,2`).
- `CHANGELOG.md` under Unreleased. Prose: US English, no em dashes, no project name in prose.

## Left to the builder

- The parser's shape (regex plus loop, or a small scanner).
- Test fixture names and any extra anchors.
