<?php

declare(strict_types=1);

namespace App\Service\Statistics;

/**
 * Resolved date window for the statistics dashboard.
 *
 * The boundaries are half-open: queries should use `>= start` and `< end`,
 * which lets calendar presets (e.g. "this month") naturally extend through
 * the final day. Both boundaries are stored as DateTimeImmutable in the
 * application's local timezone - display formatting goes through
 * DateFormatter.
 */
final class StatisticsPeriod
{
    public function __construct(
        public readonly string $preset,
        public readonly \DateTimeImmutable $start,
        public readonly \DateTimeImmutable $end,
    ) {
    }

    /**
     * Approximate day span - used to pick the chart granularity.
     */
    public function dayCount(): int
    {
        $diff = $this->start->diff($this->end);

        return (int) $diff->days;
    }

    public function isCustom(): bool
    {
        return $this->preset === StatisticsPeriodResolver::PRESET_CUSTOM;
    }
}
