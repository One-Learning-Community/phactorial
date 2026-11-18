<?php

declare(strict_types=1);

/**
 * Renders each sample in tests/fixtures/github/cases.php with GitHub's own renderer and saves the HTML next to it.
 * Run by hand with an authenticated `gh`; it only POSTs the generic sample strings to /markdown (a read-only call).
 * The output is committed, so the tests never touch the network.
 */
$dir = __DIR__ . '/../tests/fixtures/github';
/** @var array<string, string> $cases */
$cases = require $dir . '/cases.php';

foreach ($cases as $name => $markdown) {
    $command = sprintf('gh api -X POST /markdown -f mode=gfm -f text=%s 2>&1', escapeshellarg($markdown));
    exec($command, $lines, $status);
    $html = implode("\n", $lines);
    $lines = [];
    if ($status !== 0) {
        fwrite(STDERR, "GitHub rejected '$name': $html\n");
        exit(1);
    }
    // The run id is random per request; dropping it keeps the committed files stable.
    $html = preg_replace('/ data-run-id="[^"]*"/', '', $html) ?? $html;
    file_put_contents("$dir/$name.html", $html . "\n");
    echo "captured $name\n";
    usleep(300000);
}
