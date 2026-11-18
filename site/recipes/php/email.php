<?php

use Phactorial\MathRender;

$png = MathRender::fromTex('E = mc^2')->png();

echo $png->img('cid:formula-1');
