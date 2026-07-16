<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Statistics;

use App\Service\Statistics\StatisticsPeriodResolver;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;

class StatisticsPeriodResolverTest extends TestCase
{
    public function testDefaultsToThisMonthWhenNoRangeRequested(): void
    {
        $now = new \DateTimeImmutable('2026-04-15 10:30:00');
        $period = (new StatisticsPeriodResolver())->resolve(new Request(), $now);

        $this->assertSame(StatisticsPeriodResolver::PRESET_THIS_MONTH, $period->preset);
        $this->assertSame('2026-04-01', $period->start->format('Y-m-d'));
        $this->assertSame('2026-05-01', $period->end->format('Y-m-d'));
    }

    public function testTodayPresetUsesHalfOpenDayBoundary(): void
    {
        $now = new \DateTimeImmutable('2026-04-15 13:00:00');
        $period = (new StatisticsPeriodResolver())->resolve(
            new Request(['range' => 'today']),
            $now,
        );

        $this->assertSame('2026-04-15', $period->start->format('Y-m-d'));
        $this->assertSame('2026-04-16', $period->end->format('Y-m-d'));
        $this->assertSame('00:00:00', $period->start->format('H:i:s'));
    }

    public function testLastMonthPreset(): void
    {
        $now = new \DateTimeImmutable('2026-04-15');
        $period = (new StatisticsPeriodResolver())->resolve(
            new Request(['range' => 'last_month']),
            $now,
        );

        $this->assertSame('2026-03-01', $period->start->format('Y-m-d'));
        $this->assertSame('2026-04-01', $period->end->format('Y-m-d'));
    }

    public function testCustomRangeIncludesEndDay(): void
    {
        $period = (new StatisticsPeriodResolver())->resolve(
            new Request(['range' => 'custom', 'from' => '2026-03-10', 'to' => '2026-03-20']),
            new \DateTimeImmutable('2026-04-15'),
        );

        $this->assertSame(StatisticsPeriodResolver::PRESET_CUSTOM, $period->preset);
        $this->assertSame('2026-03-10', $period->start->format('Y-m-d'));
        // End is exclusive - last selected day + 1.
        $this->assertSame('2026-03-21', $period->end->format('Y-m-d'));
    }

    public function testInvalidCustomRangeFallsBackToDefault(): void
    {
        $now = new \DateTimeImmutable('2026-04-15');
        $period = (new StatisticsPeriodResolver())->resolve(
            new Request(['range' => 'custom', 'from' => '2026-04-20', 'to' => '2026-04-10']),
            $now,
        );

        // Inverted range → falls back to "this month".
        $this->assertSame(StatisticsPeriodResolver::PRESET_THIS_MONTH, $period->preset);
    }

    public function testMissingCustomDatesFallsBackToDefault(): void
    {
        $now = new \DateTimeImmutable('2026-04-15');
        $period = (new StatisticsPeriodResolver())->resolve(
            new Request(['range' => 'custom']),
            $now,
        );

        $this->assertSame(StatisticsPeriodResolver::PRESET_THIS_MONTH, $period->preset);
    }

    public function testUnknownPresetFallsBackToDefault(): void
    {
        $now = new \DateTimeImmutable('2026-04-15');
        $period = (new StatisticsPeriodResolver())->resolve(
            new Request(['range' => 'forever']),
            $now,
        );

        $this->assertSame(StatisticsPeriodResolver::PRESET_THIS_MONTH, $period->preset);
    }
}
