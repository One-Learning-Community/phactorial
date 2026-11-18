<?php

declare(strict_types=1);

namespace Phactorial\Recipes\Tests;

/** Runs a documented snippet the way a reader would paste it. */
final class Snippet
{
    /**
     * Includes $path, relative to the recipes directory, in a fresh scope holding $vars.
     *
     * @param array<string, mixed> $vars
     * @return array{output: string, vars: array<string, mixed>}
     */
    public static function run(string $path, array $vars = []): array
    {
        $file = dirname(__DIR__) . '/' . $path;

        return (static function () use ($file, $vars): array {
            extract($vars);
            ob_start();
            try {
                include $file;
            } finally {
                $output = (string) ob_get_clean();
            }
            $defined = get_defined_vars();
            unset($defined['file'], $defined['vars'], $defined['output']);

            return ['output' => $output, 'vars' => $defined];
        })();
    }
}
