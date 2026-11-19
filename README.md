<h1>
  <picture>
    <source media="(prefers-color-scheme: dark)" srcset="site/src/.vuepress/public/phactorial-wordmark-dark.svg">
    <img alt="Phactorial" src="site/src/.vuepress/public/phactorial-wordmark.svg" height="56">
  </picture>
</h1>

**Equations made simple.**

[![phactorial/phactorial](https://img.shields.io/packagist/v/phactorial/phactorial?label=phactorial/phactorial)](https://packagist.org/packages/phactorial/phactorial) [![phactorial/commonmark](https://img.shields.io/packagist/v/phactorial/commonmark?label=phactorial/commonmark)](https://packagist.org/packages/phactorial/commonmark) [![core](https://github.com/One-Learning-Community/phactorial/actions/workflows/core.yml/badge.svg?branch=main)](https://github.com/One-Learning-Community/phactorial/actions/workflows/core.yml) [![commonmark](https://github.com/One-Learning-Community/phactorial/actions/workflows/commonmark.yml/badge.svg?branch=main)](https://github.com/One-Learning-Community/phactorial/actions/workflows/commonmark.yml) [![recipes](https://github.com/One-Learning-Community/phactorial/actions/workflows/recipes.yml/badge.svg?branch=main)](https://github.com/One-Learning-Community/phactorial/actions/workflows/recipes.yml)

Math typesetting in plain PHP. It reads TeX and MathML and writes SVG, PNG, MathML, TeX, Word equations and text. It needs PHP 8.4 or later, and no Node, browser or fonts on the server. PNG output also needs `ext-imagick`.

```php
use Phactorial\MathRender;

echo MathRender::fromTex('x = \frac{-b \pm \sqrt{b^2 - 4ac}}{2a}')->display()->svg();
```

## Packages

This repository holds two Composer packages.

- `phactorial/phactorial` (`packages/core`): the equation builder, TeX and MathML readers, layout and renderers.
- `phactorial/commonmark` (`packages/commonmark`): a CommonMark extension that reads math in Markdown.

```
composer require phactorial/phactorial
composer require phactorial/commonmark
```

## Documentation

Guides and recipes are at https://one-learning-community.github.io/phactorial/.

## Contributing

See `CONTRIBUTING.md`. The code is MIT licensed; `NOTICE.md` lists the bundled font.
