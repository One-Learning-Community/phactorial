<?php

use Phactorial\MathRender;

$mathml = MathRender::fromTex('\sqrt{x^2 + 1}')->mathml();
$tex = MathRender::fromMathml('<math><mfrac><mi>a</mi><mi>b</mi></mfrac></math>')->tex();   // \frac{a}{b}
