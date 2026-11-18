<?php

use Illuminate\Support\Str;
use Phactorial\CommonMark\MathExtension;

$html = Str::markdown($post->body, ['math' => ['output' => 'mathml']], [new MathExtension()]);
