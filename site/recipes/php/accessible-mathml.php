<?php

use Phactorial\MathRender;

$formula = MathRender::fromTex('a^2 + b^2 = c^2');

echo $formula->svg(label: '') . '<span class="visually-hidden">' . $formula->mathml() . '</span>';
