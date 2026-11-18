<?php

declare(strict_types=1);

namespace Phactorial\Recipes\Tests;

use App\Markdown\EmailMath;
use Illuminate\Mail\Markdown;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Phactorial\CommonMark\MathExtension;
use Phactorial\MathRender;
use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use Spatie\LaravelMarkdown\MarkdownRenderer;

/** The recipes on the Laravel page, run as written there. */
final class LaravelRecipesTest extends LaravelTestCase
{
    public function test_blade_component_matches_render_svg(): void
    {
        self::assertSame(MathRender::fromTex('\pi r^2')->svg(), trim(Blade::render('<x-math tex="\pi r^2" />')));
        self::assertSame(
            MathRender::fromTex('\pi r^2')->display()->svg(),
            trim(Blade::render('<x-math tex="\pi r^2" display />')),
        );
    }

    public function test_str_markdown_writes_mathml(): void
    {
        $html = (string) Snippet::run('laravel/str-markdown.php', ['post' => (object) ['body' => 'Area $\pi r^2$.']])['vars']['html'];

        self::assertStringContainsString('<math', $html);
        self::assertStringNotContainsString('<svg', $html);
    }

    public function test_spatie_renders_math(): void
    {
        config([
            'markdown.extensions' => [MathExtension::class],
            'markdown.commonmark_options' => ['math' => ['output' => 'mathml']],
            'markdown.code_highlighting.enabled' => false,
        ]);

        self::assertStringContainsString('<math', app(MarkdownRenderer::class)->toHtml('$x^2$'));
    }

    #[RequiresPhpExtension('imagick')]
    public function test_email_math_links_stored_pngs(): void
    {
        $disk = Storage::fake('public');
        config(['mail.markdown.extensions' => [MathExtension::class, EmailMath::class]]);
        app(Markdown::class); // the mail Markdown class reads its extensions when the container builds it

        $text = "Area \$\\pi r^2\$.\n\n\$\$\nx^2\n\$\$\n\n```math\ny\n```\n\n```php\necho 1;\n```\n";
        $html = (string) Markdown::parse($text);

        preg_match_all('/<img [^>]*src="([^"]+)"/', $html, $sources);
        self::assertCount(3, $sources[1]);
        foreach ($sources[1] as $src) {
            $path = 'math/' . basename(parse_url($src, PHP_URL_PATH));
            self::assertTrue($disk->exists($path), $path);
            self::assertStringStartsWith("\x89PNG", (string) $disk->get($path));
        }
        self::assertSame(2, substr_count($html, '<p style="text-align: center"><img'));
        self::assertStringContainsString('<code class="language-php">', $html);

        Markdown::parse($text);
        self::assertCount(3, $disk->allFiles('math'));
    }

    public function test_cache_recipe_keys_on_body(): void
    {
        config(['cache.default' => 'array']);
        $a = 'Area $\pi r^2$.';
        $b = 'Volume $\tfrac43 \pi r^3$.';

        $first = (string) Snippet::run('laravel/cache.php', ['post' => (object) ['body' => $a]])['vars']['html'];
        $second = (string) Snippet::run('laravel/cache.php', ['post' => (object) ['body' => $b]])['vars']['html'];

        self::assertStringContainsString('<svg', $first);
        self::assertTrue(Cache::has('markdown:' . hash('xxh128', $a)));
        self::assertNotSame($first, $second);
    }
}
