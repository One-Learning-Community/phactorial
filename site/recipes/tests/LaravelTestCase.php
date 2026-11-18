<?php

declare(strict_types=1);

namespace Phactorial\Recipes\Tests;

use Orchestra\Testbench\TestCase;
use Spatie\LaravelMarkdown\MarkdownServiceProvider;

abstract class LaravelTestCase extends TestCase
{
    protected function getPackageProviders($app): array
    {
        return [MarkdownServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('view.paths', [dirname(__DIR__) . '/laravel']);
    }
}
