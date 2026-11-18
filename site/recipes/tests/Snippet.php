<?php

declare(strict_types=1);

namespace Phactorial\Recipes\Tests;

/** Runs a documented snippet the way a reader would paste it. */
final class Snippet
{
    /**
     * Includes $path, relative to the recipes directory, in a fresh scope holding $vars.
     *
     * The scope holds nothing else, so a snippet may use any variable name. Arguments arrive through
     * func_get_arg() for that reason.
     *
     * @param array<string, mixed> $vars
     * @return array{output: string, vars: array<string, mixed>}
     */
    public static function run(string $path, array $vars = []): array
    {
        ob_start();
        try {
            $defined = (static function (): array {
                extract(func_get_arg(1));
                include func_get_arg(0);

                return get_defined_vars();
            })(dirname(__DIR__) . '/' . $path, $vars);
        } finally {
            $output = (string) ob_get_clean();
        }

        return ['output' => $output, 'vars' => $defined];
    }
}
