<?php

namespace App\Tests\Unit\Service;

use App\Entity\Workshop\WorkOrder;
use App\Enum\MileageUnit;
use App\Service\Settings\SettingsProvider;
use App\Service\Workshop\WorkOrderMileageManager;
use App\Settings\SettingsRegistry;
use PHPUnit\Framework\TestCase;

class WorkOrderMileageManagerTest extends TestCase
{
    public function testAttachesCurrentRegionalUnitWhenReadingIsNewlySet(): void
    {
        $workOrder = new WorkOrder();
        $workOrder->setOdometerReading(120000);

        $this->managerWithUnit('km')->applySnapshot($workOrder);

        $this->assertSame(MileageUnit::Kilometres, $workOrder->getOdometerUnit());
    }

    public function testPreservesExistingUnitEvenWhenRegionalChanged(): void
    {
        $workOrder = new WorkOrder();
        $workOrder->setOdometerReading(120000);
        $workOrder->setOdometerUnit(MileageUnit::Kilometres);

        $this->managerWithUnit('mi')->applySnapshot($workOrder);

        // The historical km unit must NOT be relabelled to mi.
        $this->assertSame(MileageUnit::Kilometres, $workOrder->getOdometerUnit());
    }

    public function testClearsUnitWhenReadingIsRemoved(): void
    {
        $workOrder = new WorkOrder();
        $workOrder->setOdometerReading(null);
        $workOrder->setOdometerUnit(MileageUnit::Kilometres);

        $this->managerWithUnit('km')->applySnapshot($workOrder);

        $this->assertNull($workOrder->getOdometerUnit());
    }

    public function testOldWorkOrderWithoutMileageUsesCurrentSettingOnFirstReading(): void
    {
        $workOrder = new WorkOrder(); // never had a reading
        $workOrder->setOdometerReading(50000);

        $this->managerWithUnit('mi')->applySnapshot($workOrder);

        $this->assertSame(MileageUnit::Miles, $workOrder->getOdometerUnit());
    }

    public function testResolveDefaultUnitFallsBackWhenSettingMissingOrInvalid(): void
    {
        $this->assertSame(MileageUnit::DEFAULT, $this->managerWithUnit(null)->resolveDefaultUnit());
        $this->assertSame(MileageUnit::DEFAULT, $this->managerWithUnit('parsecs')->resolveDefaultUnit());
    }

    private function managerWithUnit(?string $stored): WorkOrderMileageManager
    {
        $provider = $this->createStub(SettingsProvider::class);
        $provider->method('getString')->willReturnMap([
            [SettingsRegistry::SECTION_REGIONAL, SettingsRegistry::KEY_MILEAGE_UNIT, $stored],
        ]);

        return new WorkOrderMileageManager($provider);
    }
}
