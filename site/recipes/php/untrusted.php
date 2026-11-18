<?php

use Phactorial\MathRender;
use Phactorial\NoteLevel;

$formula = MathRender::fromTex($userInput);

foreach ($formula->notes() as $note) {
    if ($note->level === NoteLevel::Dropped) {
        // Part of the formula was not drawn. Tell the author.
    }
}
