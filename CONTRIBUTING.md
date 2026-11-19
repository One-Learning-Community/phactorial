# Contributing to Phactorial

This is a monorepo with two Composer packages:

- `packages/core` (`phactorial/phactorial`): the equation builder, layout, readers and renderers.
- `packages/commonmark` (`phactorial/commonmark`): the CommonMark extension. It requires core through a path
  repository (`../core`, symlinked), so a change in core is visible in the extension immediately.

## Development setup

You need PHP 8.4+ with `mbstring`, `dom`, and Composer 2. `imagick` is optional: the PNG renderer's tests skip without it.

Install both packages:

```
composer --working-dir=packages/core install
composer --working-dir=packages/commonmark install
```

The extension's path repository pins core to the current minor's `x-dev` version (`options.versions` in its
`composer.json`), so it resolves on any branch or detached checkout. Bump that pin together with core's
`branch-alias` and the `phactorial/phactorial` requirement when the minor version changes. The recipe tests in
`site/recipes` pin both packages the same way and need the same bump.

## Running the tests

Run each package's suite and static analysis from its own directory (PHPStan runs at level 8 and must be clean):

```
cd packages/core
vendor/bin/phpunit
vendor/bin/phpstan analyse --memory-limit=1G

cd ../commonmark
vendor/bin/phpunit
vendor/bin/phpstan analyse --memory-limit=1G
```

Each package keeps a manifest of its public API in `tests/Api/api.txt`: one sorted line per public class, method, property, constant and enum case, with types. `ApiManifestTest` fails when the code and the manifest disagree. After an intended change, regenerate it with `UPDATE_API=1 vendor/bin/phpunit --filter ApiManifestTest` and commit the file with the change. A diff in `api.txt` is a public API change and needs a line in the CHANGELOG.

If Xdebug is loaded, prefix with `php -d xdebug.mode=off` for speed. CI runs the same commands on PHP 8.4 and 8.5
(`.github/workflows`).

The core package's fuzz tests take about ten seconds and are left out of the default run; CI runs them on PHP 8.4.
Run them after changing the TeX or chemistry readers, the MathML reader or writer, or operator spacing:

```
cd packages/core && vendor/bin/phpunit --group fuzz
```

The MathML round-trip tests (`tests/Mathml/RoundTripFuzzTest.php`) pin the exact set of known mismatches as a count
and a hash. If your change moves a set, run with `ROUND_TRIP_DUMP=<dir>` at the parent commit and at yours, and diff
the files. Then update the constant in the same commit and give the reason in the commit message.

The docs import their runnable recipes from `site/recipes`, a Composer project on Orchestra Testbench that runs each
one as a test. Run it after changing either package or a recipe:

```
cd site/recipes
composer install
vendor/bin/phpunit
```

The PNG recipes skip without `imagick` locally; CI requires it. A recipe file shows on its page from the line its
`@[code{N-} php]` import names, so keep the lines above that to the opening tag, blank lines and
`use Phactorial\MathRender;`. `DocImportsTest` enforces this.

`bin/benchmark.php` times typesetting in fresh processes without opcache, the worst case a request meets. Run it
with `php bin/benchmark.php --runs=20` after `packages/commonmark` is installed. The speed figures in the docs come
from it.

## Compatibility

[Stability](https://one-learning-community.github.io/phactorial/stability.html) is the policy. In short:

- A minor release adds: node types, builder methods, optional parameters, config values, supported commands, and better output.
- A major release breaks: removed or renamed public symbols, renamed parameters or changed defaults, changed return types, a new `NoteLevel` case, a lowered input limit, and dropped TeX or MathML support.
- Anything marked `@internal` carries no promise. New engine detail gets `@internal Engine detail, not public API.`
- `Node` and `DisplayItem` are sealed. Code that switches on node types needs a default branch.
- A change in `tests/Api/api.txt` needs a CHANGELOG line, and a change to golden output needs one too.

Tests come first: write the failing test, watch it fail, then implement.

## Style

- `declare(strict_types=1)` everywhere; types on every signature; nodes are immutable (methods return copies).
- No new runtime dependencies without discussion.
- Error messages say what is wrong, where, and what to do about it.

## Releasing

Releases are tag-driven. Packagist reads one `composer.json` per repository, so each package is mirrored into its own
read-only split repository: `One-Learning-Community/phactorial-core` and
`One-Learning-Community/phactorial-commonmark`. Never commit to the split repos directly; they are overwritten on
every split.

| Workflow | Trigger | Does |
|---|---|---|
| `core.yml`, `commonmark.yml` | push to `main` and pull requests, when the package's files change | tests and PHPStan |
| `recipes.yml` | push to `main` and pull requests, when either package or `site/recipes` changes | runs the docs' recipes |
| `split.yml` | push to `main` | mirrors `packages/*` to the split repos (their `dev-main`) |
| `release.yml` | tag `vX.Y.Z` | all three suites, CHANGELOG check, split and tag both packages, GitHub release |
| `docs.yml` | push to `main` and pull requests, when the site changes | builds the site; deploys to GitHub Pages only from `main` |

### One-time setup

1. Create a fine-grained personal access token (resource owner `One-Learning-Community`) with
   **Contents: read and write** on `phactorial-core` and `phactorial-commonmark` only, and save it as the
   `SPLIT_TOKEN` Actions secret on `One-Learning-Community/phactorial`. Without it `split.yml` skips with a notice
   and `release.yml` fails at the split step.
2. Initialize each split repo (`phactorial-core`, `phactorial-commonmark`) with a README on the default branch
   `main`. The split action cannot push to an empty repository.
3. In `One-Learning-Community/phactorial`, set Settings → Pages → Source to **GitHub Actions**, so `docs.yml` can
   deploy.
4. Once the split repos have content, submit them at https://packagist.org/packages/submit (`phactorial-core`
   first, since the extension requires it, then `phactorial-commonmark`). Packagist's GitHub integration keeps them
   updated on new pushes and tags.

### Each release

1. Make sure `main` is green.
2. In `CHANGELOG.md` rename `## [Unreleased]` to `## [X.Y.Z] - YYYY-MM-DD` (`release.yml` refuses a missing or
   still-unreleased section), and add a fresh `## [Unreleased]` above it.
3. If the minor version changed, bump the `extra.branch-alias` of both packages, the `versions` pin in the
   extension's path repository, the extension's `phactorial/phactorial` requirement (`^X.Y`), and both `versions`
   pins in `site/recipes/composer.json`.
4. Commit, then tag from `main`: `git tag -a vX.Y.Z -m "Phactorial X.Y.Z" && git push origin main vX.Y.Z`.
5. Verify in a scratch project: `composer require phactorial/phactorial phactorial/commonmark`, then render one
   formula.

The extension's `repositories` and `minimum-stability` entries exist for monorepo development. Composer ignores both
in non-root packages, so they do not affect consumers of the split repo.
