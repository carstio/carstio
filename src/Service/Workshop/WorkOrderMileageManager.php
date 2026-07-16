<?php

namespace App\Service\Workshop;

use App\Entity\Workshop\WorkOrder;
use App\Enum\MileageUnit;
use App\Service\Settings\SettingsProvider;
use App\Settings\SettingsRegistry;

/**
 * Keeps the (odometerReading, odometerUnit) pair coherent on a WorkOrder.
 *
 * The unit must be a snapshot taken at the time the reading is first
 * recorded - otherwise a later change to the Regional setting would
 * silently relabel old readings (today's "120000 km" would become
 * "120000 mi"). The manager is therefore the single place that decides
 * which unit to attach to a given reading.
 *
 * Rules:
 *   - reading present + no saved unit yet -> attach current Regional unit
 *   - reading present + saved unit        -> preserve the saved unit
 *   - reading missing                     -> clear the unit too
 *
 * The Regional setting itself is read here (not in WorkOrderFactory) so
 * the snapshot lands at the moment the user actually enters a number, not
 * at WorkOrder creation - the converter path can create work orders with
 * no mileage that get filled in later.
 */
class WorkOrderMileageManager
{
    public function __construct(
        private readonly SettingsProvider $settings,
    ) {
    }

    /**
     * Applied after the form has populated the entity but before flushing.
     * Idempotent: calling it on an unchanged entity does nothing.
     */
    public function applySnapshot(WorkOrder $workOrder): void
    {
        $reading = $workOrder->getOdometerReading();

        if ($reading === null) {
            $workOrder->setOdometerUnit(null);

            return;
        }

        if ($workOrder->getOdometerUnit() === null) {
            $workOrder->setOdometerUnit($this->resolveDefaultUnit());
        }
    }

    /**
     * Returns the current Regional default unit, with a safe fallback when
     * the stored value is missing or unknown (e.g. legacy data).
     */
    public function resolveDefaultUnit(): MileageUnit
    {
        $raw = $this->settings->getString(
            SettingsRegistry::SECTION_REGIONAL,
            SettingsRegistry::KEY_MILEAGE_UNIT,
        );

        if ($raw === null || $raw === '') {
            return MileageUnit::DEFAULT;
        }

        return MileageUnit::tryFrom($raw) ?? MileageUnit::DEFAULT;
    }
}
