<?php

use Phactorial\MathRender;

echo '<p>The area of a circle is ' . MathRender::fromTex('\pi r^2')->svg() . '.</p>';

echo '<p class="equation">'
    . MathRender::fromTex('\sum_{n=1}^\infty \frac{1}{n^2} = \frac{\pi^2}{6}')->display()->svg(size: 18, pad: 2)
    . '</p>';
