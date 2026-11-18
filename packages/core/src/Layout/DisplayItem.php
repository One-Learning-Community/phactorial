<?php

declare(strict_types=1);

namespace Phactorial\Layout;

/**
 * One drawing primitive. Coordinates are points, origin at the formula's left edge on its baseline, y grows DOWN.
 *
 * Sealed: the library's own types only. Extending or implementing it is unsupported, and a minor release may add a type.
 */
interface DisplayItem
{
    /** This item moved by $dx and $dy points, as a new item; the original is unchanged. */
    public function translated(float $dx, float $dy): static;
}
