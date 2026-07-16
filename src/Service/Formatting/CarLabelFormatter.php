<?php

declare(strict_types=1);

namespace App\Service\Formatting;

use App\Entity\Car\Car;

/**
 * Renders the canonical "Brand Model (PLATE)" car label used by entity-type
 * autocompletes and by single-line UIs (calendar tooltips, etc.).
 *
 * Layout-driven variants - table mid-dot, stacked two-line, plate-only -
 * stay in their templates; this formatter owns the inline parenthetical
 * form only.
 */
final class CarLabelFormatter
{
    public function label(?Car $car): string
    {
        if ($car === null) {
            return '';
        }

        $name = trim($car->getDisplayName());
        $plate = $car->getLicensePlate();

        if ($plate !== null && $plate !== '') {
            return $name === '' ? $plate : $name . ' (' . $plate . ')';
        }

        return $name;
    }
}
