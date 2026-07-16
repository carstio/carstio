<?php

namespace App\Tests\Unit\Enum;

use App\Enum\MileageUnit;
use PHPUnit\Framework\TestCase;

class MileageUnitTest extends TestCase
{
    public function testStoredValuesAreStable(): void
    {
        // Persisted on WorkOrder.odometerUnit - changing these breaks data.
        $this->assertSame('km', MileageUnit::Kilometres->value);
        $this->assertSame('mi', MileageUnit::Miles->value);
    }

    public function testValuesListMatchesEnumCases(): void
    {
        $this->assertSame(['km', 'mi'], MileageUnit::values());
    }

    public function testChoicesAreLabelKeyToValue(): void
    {
        $choices = MileageUnit::choices();
        $this->assertSame('km', $choices['mileage.unit.km'] ?? null);
        $this->assertSame('mi', $choices['mileage.unit.mi'] ?? null);
    }

    public function testTryFromUnknownReturnsNull(): void
    {
        $this->assertNull(MileageUnit::tryFrom('parsecs'));
    }
}
