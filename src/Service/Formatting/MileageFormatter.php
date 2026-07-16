<?php

declare(strict_types=1);

namespace App\Service\Formatting;

use App\Entity\Workshop\WorkOrder;
use App\Enum\MileageUnit;

/**
 * Renders odometer readings with thousands grouping and a unit suffix.
 *
 * Always uses the saved {@see WorkOrder::$odometerUnit} snapshot, never
 * the current Regional setting - otherwise historical readings would
 * silently relabel themselves when the workshop changes the default unit.
 *
 * Returns an empty string for null inputs so templates can drop the
 * filter inline ({% if work_order.odometerReading %}...{% endif %} is
 * still cleaner for surrounding markup, but the formatter never throws).
 */
final class MileageFormatter
{
    /**
     * Format a WorkOrder's saved reading + unit pair. Returns '' when the
     * reading is null. When the unit is somehow null but the reading is
     * not (legacy/imported data), falls back to {@see MileageUnit::DEFAULT}
     * so we never show a bare unitless number.
     */
    public function format(WorkOrder $workOrder): string
    {
        return $this->formatPair($workOrder->getOdometerReading(), $workOrder->getOdometerUnit());
    }

    /**
     * Format an arbitrary reading + unit pair. Exposed for places that
     * need to render mileage outside the WorkOrder shape (tests, future
     * imported readings, ad-hoc usage).
     */
    public function formatPair(?int $reading, ?MileageUnit $unit): string
    {
        if ($reading === null) {
            return '';
        }

        $unit ??= MileageUnit::DEFAULT;

        return number_format($reading, 0, '.', ',') . ' ' . $unit->value;
    }
}
