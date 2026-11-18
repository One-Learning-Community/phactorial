@props(['tex', 'display' => false])
@php($math = \Phactorial\MathRender::fromTex($tex))
{!! ($display ? $math->display() : $math)->svg() !!}
