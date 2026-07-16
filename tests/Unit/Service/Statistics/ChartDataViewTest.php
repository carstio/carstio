<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Statistics;

use App\Service\Statistics\View\ChartDataView;
use PHPUnit\Framework\TestCase;

class ChartDataViewTest extends TestCase
{
    public function testIsEmptyWhenNoLabels(): void
    {
        $chart = new ChartDataView('line', [], []);

        $this->assertTrue($chart->isEmpty());
    }

    public function testIsEmptyWhenAllValuesAreZero(): void
    {
        $chart = new ChartDataView('line', ['Apr 1', 'Apr 2'], [0, 0]);

        $this->assertTrue($chart->isEmpty());
    }

    public function testIsNotEmptyWithAnyNonZeroValue(): void
    {
        $chart = new ChartDataView('line', ['Apr 1', 'Apr 2'], [0, 12.5]);

        $this->assertFalse($chart->isEmpty());
    }

    public function testToArrayShapeMatchesStimulusContract(): void
    {
        $chart = new ChartDataView(
            type: 'doughnut',
            labels: ['Cash', 'Card'],
            values: [50, 83],
            isMoney: true,
            currencyCode: 'EUR',
        );

        $this->assertSame(
            [
                'type' => 'doughnut',
                'labels' => ['Cash', 'Card'],
                'values' => [50, 83],
                'isMoney' => true,
                'currencyCode' => 'EUR',
            ],
            $chart->toArray(),
        );
    }
}
