<?php

use Phactorial\MathRender;

echo MathRender::fromTex('x = \frac{-b \pm \sqrt{b^2 - 4ac}}{2a}')->svg();
// <svg role="img" aria-label="x = (−b ± √(b² − 4ac))/(2a)" …>
