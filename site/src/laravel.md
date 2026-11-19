---
description: Render math in Laravel Markdown on the server, as SVG or MathML, with no JavaScript and no math font. Works with Str::markdown(), mail, spatie/laravel-markdown and Blade.
---

# Laravel

Render math in Laravel's Markdown on the server: `$x^2$` and `$$…$$` become SVG or MathML, with no JavaScript and no math font.

Laravel renders Markdown through league/commonmark in three places: `Str::markdown()`, mail templates and spatie/laravel-markdown. Each accepts [phactorial/commonmark](./commonmark.md). Mail needs one extra class, because email cannot show SVG. Outside Markdown, a Blade component typesets a formula in any template.

```
composer require phactorial/commonmark
```

## Str::markdown()

`Str::markdown()` and `Str::inlineMarkdown()` take options and extensions as their second and third arguments:

@[code{3-} php](../recipes/laravel/str-markdown.php)

The fluent form takes the same arguments: `str($post->body)->markdown([], [new MathExtension()])`. Print the result unescaped, with `{!! $html !!}`. The extension escapes everything it writes, but the rest of the Markdown can still carry raw HTML. For text your users wrote, pass `'html_input' => 'strip'`.

The [options](./commonmark.md#options) live under the `math` key, beside league/commonmark's own.

## spatie/laravel-markdown

Add the extension to `config/markdown.php`, and its options to `commonmark_options`:

```php
'commonmark_options' => [
    'math' => ['output' => 'mathml'],
],

'extensions' => [
    Phactorial\CommonMark\MathExtension::class,
],
```

`<x-markdown>` and the `MarkdownRenderer` class then typeset math, and the package caches what it renders. Its code highlighting skips ` ```math ` fences.

## A Blade component

For a formula in a template, outside any Markdown, make an anonymous component at `resources/views/components/math.blade.php`:

@[code blade](../recipes/laravel/components/math.blade.php)

```blade
<p>The area of a circle is <x-math tex="\pi r^2" />.</p>

<x-math tex="\int_0^\infty e^{-x^2}\,dx = \frac{\sqrt{\pi}}{2}" display />
```

The SVG takes the text color, and inline it sits on the baseline of the line around it. [Output](./output.md) lists the other formats and their options.

## Mail

Gmail and Outlook remove SVG from email, and Gmail blocks `data:` images, so mail needs PNG files on a server. Mail templates have their own Markdown converter. It reads extension classes from `mail.markdown.extensions` in `config/mail.php`:

```php
'markdown' => [
    'extensions' => [
        Phactorial\CommonMark\MathExtension::class,
        App\Markdown\EmailMath::class,
    ],
],
```

`MathExtension` finds the math. `EmailMath` replaces its renderer with one that draws each formula as a PNG, saves it to the public disk and links it:

@[code php](../recipes/laravel/EmailMath.php)

Then write math in any Markdown mail template:

```blade
<x-mail::message>
The area of a circle is $\pi r^2$.

$$
\int_0^\infty e^{-x^2}\,dx = \frac{\sqrt{\pi}}{2}
$$
</x-mail::message>
```

PNG needs the Imagick extension. Run `php artisan storage:link` once, and set `APP_URL` to an address mail readers can reach, since the links must be absolute. Each file is named by its contents, so a formula sent a thousand times is stored once.

Some clients, Outlook among them, hold back remote images until the reader allows them. Until then the reader sees each formula's `alt` text, such as `πr²`.

A one-off mail can attach its images instead of linking them: render with `png()`, attach the bytes, and point `img()` at the `cid:` reference. [The email recipe](./recipes.md#in-email) shows the calls.

## Caching

A page of 50 formulas takes about 30 milliseconds to typeset the first time a PHP process draws it, without opcache, and under 10 milliseconds after that. For pages read far more often than they change, cache the HTML under a hash of the source:

@[code{3-} php](../recipes/laravel/cache.php)

A changed post gets a new key, so nothing needs clearing, and the old entry expires within the week. Clear the cache after you change the `math` options or upgrade Phactorial. spatie/laravel-markdown caches this way already.
