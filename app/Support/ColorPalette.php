<?php

namespace App\Support;

/**
 * The auxiliary colors approved for projects/tags/status indicators
 * (docs/UI-UX.md §5). Does not include the Primary brand/action color
 * (#635BFF). Single source of truth shared by Project and Tag so neither
 * model duplicates the other's palette.
 */
final class ColorPalette
{
    /**
     * @var list<string>
     */
    public const array AUXILIARY = [
        '#06B6D4',
        '#14B8A6',
        '#EC4899',
        '#F59E0B',
        '#22C55E',
        '#3B82F6',
    ];
}
