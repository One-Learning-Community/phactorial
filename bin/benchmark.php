<?php

/**
 * Times formula typesetting in fresh PHP processes, without opcache.
 *
 *     php bin/benchmark.php [--runs=N]
 *
 * Each cold scenario runs in a new process, so its time includes loading classes and the font: the worst case a
 * request can meet. The warm rows reuse one process. Numbers vary by machine.
 */

declare(strict_types=1);

const CORPUS = [
    'x^2 + y^2 = z^2',
    '\frac{a + b}{c - d}',
    '\sqrt{b^2 - 4ac}',
    '\sum_{n=1}^\infty \frac{1}{n^2} = \frac{\pi^2}{6}',
    '\int_0^\infty e^{-x^2}\,dx = \frac{\sqrt{\pi}}{2}',
    '\begin{pmatrix} a & b \\ c & d \end{pmatrix}',
    '\left( \frac{x}{1 + \frac{1}{x}} \right)^{n}',
    '\begin{aligned} f(x) &= (x + 1)^2 \\ &= x^2 + 2x + 1 \end{aligned}',
    '\hat{\theta} = \bar{x} + \vec{v} \cdot \tilde{u}',
    'x = \frac{-b \pm \sqrt{b^2 - 4ac}}{2a}',
];

const PAGE_FORMULAS = 50;
const WARM_PASSES = 20;

$start = hrtime(true);
$args = getopt('', ['runs:', 'child:', 'png:']);
$autoload = dirname(__DIR__) . '/packages/commonmark/vendor/autoload.php';

if (!is_file($autoload)) {
    fwrite(STDERR, "Run composer install in packages/commonmark first.\n");
    exit(1);
}

if (isset($args['child'])) {
    require $autoload;
    echo json_encode(child((string) $args['child'], $start, ($args['png'] ?? '0') === '1')), "\n";
    exit(0);
}

$runs = max(1, (int) ($args['runs'] ?? 10));
$png = extension_loaded('imagick');

echo 'Date:     ', date('Y-m-d H:i'), "\n";
echo 'PHP:      ', PHP_VERSION, "\n";
echo 'System:   ', php_uname('s'), ' ', php_uname('r'), ' ', php_uname('m'), "\n";
echo "opcache:  off\n";
echo 'xdebug:   ', extension_loaded('xdebug') ? 'loaded (mode=off)' : 'not loaded', "\n";
echo 'imagick:  ', $png ? phpversion('imagick') : 'not loaded', "\n";
echo 'Runs:     ', $runs, " per cold scenario\n";
echo "Numbers vary by machine; these are cold-process worst cases.\n\n";

$rows = [];
$cold = [
    'cold-first-svg' => 'cold: first formula, SVG',
    'cold-page-svg' => 'cold: page of 50, SVG',
    'cold-page-mathml' => 'cold: page of 50, MathML',
];
if ($png) {
    $cold['cold-first-png'] = 'cold: first formula, PNG';
}

foreach ($cold as $scenario => $label) {
    $times = [];
    for ($i = 0; $i < $runs; $i++) {
        $times[] = spawn($scenario, $png)['ms'];
    }
    sort($times);
    $rows[] = [$label, median($times), max($times)];
}

$warm = [];
for ($i = 0; $i < $runs; $i++) {
    foreach (spawn('warm', $png) as $name => $ms) {
        $warm[$name][] = $ms;
    }
}
$warmLabels = ['svg' => 'warm: per formula, SVG', 'mathml' => 'warm: per formula, MathML', 'png' => 'warm: per formula, PNG', 'page' => 'warm: page of 50, SVG'];
foreach ($warmLabels as $name => $label) {
    if (isset($warm[$name])) {
        sort($warm[$name]);
        $rows[] = [$label, median($warm[$name]), max($warm[$name])];
    }
}

printf("%-28s %10s %10s\n", 'scenario', 'median ms', 'max ms');
foreach ($rows as [$label, $median, $max]) {
    printf("%-28s %10s %10s\n", $label, ms($median), ms($max));
}
if (!$png) {
    echo "\nPNG: skipped (imagick not loaded)\n";
}

/** @return array<string, float> */
function spawn(string $scenario, bool $png): array
{
    $command = [PHP_BINARY];
    if (php_ini_loaded_file() === false) {
        $command[] = '-n';
    }
    array_push(
        $command,
        '-d', 'opcache.enable_cli=0',
        '-d', 'xdebug.mode=off',
        '-d', 'display_errors=stderr', // a warning on STDOUT would corrupt the JSON
        __FILE__, "--child=$scenario", '--png=' . ($png ? '1' : '0'),
    );

    // STDERR goes to a file: a pipe left unread while STDOUT drains could fill and stall the child.
    $errors = tmpfile();
    $process = proc_open($command, [1 => ['pipe', 'w'], 2 => $errors], $pipes);
    if ($process === false) {
        throw new RuntimeException("Could not start $scenario.");
    }
    $out = (string) stream_get_contents($pipes[1]);
    fclose($pipes[1]);
    $status = proc_close($process);
    rewind($errors);
    $err = (string) stream_get_contents($errors);
    fclose($errors);
    if ($status !== 0) {
        throw new RuntimeException("$scenario failed: $err");
    }

    return json_decode($out, true, flags: JSON_THROW_ON_ERROR);
}

/** @return array<string, float> */
function child(string $scenario, int $start, bool $png): array
{
    $elapsed = static fn (int $from): float => (hrtime(true) - $from) / 1e6;

    match ($scenario) {
        'cold-first-svg' => Phactorial\MathRender::fromTex(CORPUS[4])->svg(),
        'cold-first-png' => Phactorial\MathRender::fromTex(CORPUS[4])->png(),
        'cold-page-svg' => converter('svg')->convert(page()),
        'cold-page-mathml' => converter('mathml')->convert(page()),
        'warm' => null,
        default => throw new InvalidArgumentException("Unknown scenario $scenario."),
    };

    return $scenario === 'warm' ? warm($png, $elapsed) : ['ms' => $elapsed($start)];
}

/**
 * @param Closure(int): float $elapsed
 * @return array<string, float>
 */
function warm(bool $png, Closure $elapsed): array
{
    $formats = ['svg' => static fn ($m) => $m->svg(), 'mathml' => static fn ($m) => $m->mathml()];
    if ($png) {
        $formats['png'] = static fn ($m) => $m->png();
    }

    $result = [];
    foreach ($formats as $name => $write) {
        foreach (CORPUS as $tex) {
            $write(Phactorial\MathRender::fromTex($tex));
        }
        $from = hrtime(true);
        for ($pass = 0; $pass < WARM_PASSES; $pass++) {
            foreach (CORPUS as $tex) {
                $write(Phactorial\MathRender::fromTex($tex));
            }
        }
        $result[$name] = $elapsed($from) / (WARM_PASSES * count(CORPUS));
    }

    $converter = converter('svg');
    $page = page();
    $converter->convert($page);
    $times = [];
    for ($pass = 0; $pass < WARM_PASSES; $pass++) {
        $from = hrtime(true);
        $converter->convert($page);
        $times[] = $elapsed($from);
    }
    sort($times);
    $result['page'] = median($times);

    return $result;
}

function converter(string $output): League\CommonMark\MarkdownConverter
{
    $environment = new League\CommonMark\Environment\Environment(['math' => ['output' => $output]]);
    $environment->addExtension(new League\CommonMark\Extension\CommonMark\CommonMarkCoreExtension());
    $environment->addExtension(new Phactorial\CommonMark\MathExtension());

    return new League\CommonMark\MarkdownConverter($environment);
}

/** Forty inline formulas in sentences and ten display blocks, in a fixed order. */
function page(): string
{
    $paragraphs = [];
    for ($i = 0; $i < PAGE_FORMULAS; $i++) {
        $tex = CORPUS[$i % count(CORPUS)];
        $paragraphs[] = $i % 5 === 4
            ? "\$\$\n$tex\n\$\$"
            : "Step $i of the argument uses \$$tex\$ and moves on.";
    }

    return implode("\n\n", $paragraphs) . "\n";
}

/** @param list<float> $sorted */
function median(array $sorted): float
{
    $n = count($sorted);

    return $n % 2 === 1 ? $sorted[intdiv($n, 2)] : ($sorted[$n / 2 - 1] + $sorted[$n / 2]) / 2;
}

function ms(float $value): string
{
    return $value < 1 ? sprintf('%.2f', $value) : sprintf('%.1f', $value);
}
