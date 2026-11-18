<?php

use Phactorial\MathRender;

$inline = MathRender::fromTex('a^2 + b^2 = c^2')->omml();                          // goes between runs in a paragraph
$block = MathRender::fromTex('\int_0^1 x\,dx = \tfrac12')->display()->omml();      // fills a paragraph of its own
