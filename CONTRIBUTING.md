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
`branch-alias` and the `phactorial/phactorial` requirement when the minor version changes.

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

The core package's fuzz tests (thousands of random TeX and chemistry inputs) are left out of the default run. Run
them after changing the TeX or chemistry readers: `cd packages/core && vendor/bin/phpunit --group fuzz`.

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
| `split.yml` | push to `main` | mirrors `packages/*` to the split repos (their `dev-main`) |
| `release.yml` | tag `vX.Y.Z` | both suites, CHANGELOG check, split and tag both packages, GitHub release |
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
   extension's path repository, and the extension's `phactorial/phactorial` requirement (`^X.Y`).
4. Commit, then tag from `main`: `git tag -s vX.Y.Z -m "Phactorial X.Y.Z" && git push origin main vX.Y.Z`.
5. Verify in a scratch project: `composer require phactorial/phactorial phactorial/commonmark`, then render one
   formula.

The extension's `repositories` and `minimum-stability` entries exist for monorepo development. Composer ignores both
in non-root packages, so they do not affect consumers of the split repo.
