<?php

namespace App\Tests\Unit\Service;

use App\Entity\Workshop\WorkOrder;
use App\Enum\MileageUnit;
use App\Service\Formatting\MileageFormatter;
use PHPUnit\Framework\TestCase;

class MileageFormatterTest extends TestCase
{
    public function testFormatsReadingWithThousandSeparatorAndSnapshotUnit(): void
    {
        $formatter = new MileageFormatter();

        $this->assertSame('120,000 km', $formatter->formatPair(120000, MileageUnit::Kilometres));
        $this->assertSame('74,565 mi', $formatter->formatPair(74565, MileageUnit::Miles));
    }

    public function testNullReadingReturnsEmptyString(): void
    {
        $this->assertSame('', (new MileageFormatter())->formatPair(null, MileageUnit::Kilometres));
        $this->assertSame('', (new MileageFormatter())->formatPair(null, null));
    }

    public function testFallsBackToDefaultUnitWhenUnitIsMissingButReadingIsNot(): void
    {
        // Defensive: legacy/imported row that has a reading but no saved
        // unit must still produce a non-bare number.
        $this->assertSame('1,000 km', (new MileageFormatter())->formatPair(1000, null));
    }

    public function testFormatUsesWorkOrderSnapshotNotCurrentSetting(): void
    {
        $workOrder = new WorkOrder();
        $workOrder->setOdometerReading(50000);
        $workOrder->setOdometerUnit(MileageUnit::Miles);

        $this->assertSame('50,000 mi', (new MileageFormatter())->format($workOrder));
    }
}
