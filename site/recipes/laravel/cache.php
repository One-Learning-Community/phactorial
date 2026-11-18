<?php

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Phactorial\CommonMark\MathExtension;

$html = Cache::remember(
    'markdown:' . hash('xxh128', $post->body),
    now()->addWeek(),
    fn () => Str::markdown($post->body, [], [new MathExtension()]),
);
