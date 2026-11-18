# Laravel

Laravel renders Markdown through league/commonmark in three places: `Str::markdown()`, mail templates and, if you use it, spatie/laravel-markdown. Each one accepts [phactorial/commonmark](./commonmark.md). Mail needs one class more, because email cannot show SVG. Outside Markdown, a Blade component typesets a formula in any template, and a cache keeps busy pages fast.

```
composer require phactorial/commonmark
```

## Str::markdown()

`Str::markdown()` and `Str::inlineMarkdown()` take options and extensions as their second and third arguments:

```php
use Illuminate\Support\Str;
use Phactorial\CommonMark\MathExtension;

$html = Str::markdown($post->body, ['math' => ['output' => 'mathml']], [new MathExtension()]);
```

The fluent form takes the same arguments: `str($post->body)->markdown([], [new MathExtension()])`. Print the result unescaped, with `{!! $html !!}`. The extension escapes everything it writes, but the rest of the Markdown can carry raw HTML unless you pass `'html_input' => 'strip'`, so strip it for text your users wrote.

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

`<x-markdown>` and the `MarkdownRenderer` class then typeset math, and the package caches what it renders. From phactorial/commonmark 0.19.0, its code highlighting leaves ` ```math ` fences alone and highlights every other fence.

## A Blade component

For a formula in a template, outside any Markdown, make an anonymous component at `resources/views/components/math.blade.php`:

```blade
@props(['tex', 'display' => false])
@php($math = \Phactorial\MathRender::fromTex($tex))
{!! ($display ? $math->display() : $math)->svg() !!}
```

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

```php
<?php

namespace App\Markdown;

use Illuminate\Support\Facades\Storage;
use League\CommonMark\Environment\EnvironmentBuilderInterface;
use League\CommonMark\Extension\CommonMark\Node\Block\FencedCode;
use League\CommonMark\Extension\ExtensionInterface;
use League\CommonMark\Node\Node;
use League\CommonMark\Renderer\ChildNodeRendererInterface;
use League\CommonMark\Renderer\NodeRendererInterface;
use Phactorial\CommonMark\Node\MathBlock;
use Phactorial\CommonMark\Node\MathInline;
use Phactorial\MathRender;

final class EmailMath implements ExtensionInterface, NodeRendererInterface
{
    public function register(EnvironmentBuilderInterface $environment): void
    {
        $environment
            ->addRenderer(MathInline::class, $this, 10)
            ->addRenderer(MathBlock::class, $this, 10)
            ->addRenderer(FencedCode::class, $this, 100);
    }

    public function render(Node $node, ChildNodeRendererInterface $childRenderer): ?string
    {
        [$tex, $display] = match (true) {
            $node instanceof MathInline => [$node->tex, $node->display],
            $node instanceof MathBlock => [$node->tex, true],
            $node instanceof FencedCode && ($node->getInfoWords()[0] ?? '') === 'math' => [trim($node->getLiteral()), true],
            default => [null, false],
        };

        if ($tex === null) {
            return null;
        }

        $math = MathRender::fromTex($tex);
        $png = ($display ? $math->display() : $math->inline())->png(pad: 1);

        $path = 'math/' . hash('sha256', $png->bytes) . '.png';
        Storage::disk('public')->put($path, $png->bytes);
        $img = $png->img(Storage::disk('public')->url($path));

        return $node instanceof MathInline ? $img : '<p style="text-align: center">' . $img . '</p>';
    }
}
```

Then write math in any Markdown mail template:

```blade
<x-mail::message>
The area of a circle is $\pi r^2$.

$$
\int_0^\infty e^{-x^2}\,dx = \frac{\sqrt{\pi}}{2}
$$
</x-mail::message>
```

PNG needs the Imagick extension. Run `php artisan storage:link` once, and set `APP_URL` to the address mail readers can reach, since the links must be absolute. A file is named by its contents, so a formula sent a thousand times is stored once. Some clients, Outlook among them, hold back remote images until the reader allows them; until then the reader sees each formula's `alt` text, which is the formula written out, such as `πr²`.

A mail sent once to one reader can carry its images instead of linking them: render with `png()`, attach the bytes, and point `img()` at the `cid:` reference. [The email recipe](./recipes.md#in-email) shows the calls.

## Caching

A page of 50 formulas takes about 20 milliseconds to typeset the first time a PHP process draws it, and a few milliseconds after that. For pages read far more often than they change, cache the HTML under a hash of the source:

```php
use Illuminate\Support\Facades\Cache;

$html = Cache::remember(
    'markdown:' . hash('xxh128', $post->body),
    now()->addWeek(),
    fn () => Str::markdown($post->body, [], [new MathExtension()]),
);
```

A changed post gets a new key, so nothing needs clearing, and the old entry expires within the week. Clear the cache after you change the `math` options or upgrade Phactorial. spatie/laravel-markdown caches this way already.
