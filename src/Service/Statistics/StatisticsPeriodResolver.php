<?php

declare(strict_types=1);

namespace App\Service\Statistics;

use Symfony\Component\HttpFoundation\Request;

/**
 * Translates the period filter (preset or custom range) submitted by the
 * dashboard form into a StatisticsPeriod with safe DateTimeImmutable
 * boundaries.
 *
 * Invalid, missing, or inverted custom ranges fall back to the default
 * preset rather than throwing - the dashboard should always render.
 */
final class StatisticsPeriodResolver
{
    public const PRESET_TODAY = 'today';
    public const PRESET_THIS_WEEK = 'this_week';
    public const PRESET_THIS_MONTH = 'this_month';
    public const PRESET_LAST_MONTH = 'last_month';
    public const PRESET_THIS_YEAR = 'this_year';
    public const PRESET_CUSTOM = 'custom';

    public const DEFAULT_PRESET = self::PRESET_THIS_MONTH;

    /**
     * @return list<string>
     */
    public static function presets(): array
    {
        return [
            self::PRESET_TODAY,
            self::PRESET_THIS_WEEK,
            self::PRESET_THIS_MONTH,
            self::PRESET_LAST_MONTH,
            self::PRESET_THIS_YEAR,
            self::PRESET_CUSTOM,
        ];
    }

    public function resolve(Request $request, ?\DateTimeImmutable $now = null): StatisticsPeriod
    {
        $now ??= new \DateTimeImmutable('now');
        $preset = (string) $request->query->get('range', self::DEFAULT_PRESET);

        if (!in_array($preset, self::presets(), true)) {
            $preset = self::DEFAULT_PRESET;
        }

        if ($preset === self::PRESET_CUSTOM) {
            $period = $this->resolveCustom(
                (string) $request->query->get('from', ''),
                (string) $request->query->get('to', ''),
            );

            if ($period !== null) {
                return $period;
            }

            // Invalid custom range - silently fall back to the default preset
            // rather than confusing the user with an error.
            $preset = self::DEFAULT_PRESET;
        }

        return $this->resolvePreset($preset, $now);
    }

    private function resolvePreset(string $preset, \DateTimeImmutable $now): StatisticsPeriod
    {
        $today = $now->setTime(0, 0);

        return match ($preset) {
            self::PRESET_TODAY => new StatisticsPeriod(
                $preset,
                $today,
                $today->modify('+1 day'),
            ),
            self::PRESET_THIS_WEEK => new StatisticsPeriod(
                $preset,
                $today->modify('monday this week'),
                $today->modify('monday next week'),
            ),
            self::PRESET_LAST_MONTH => new StatisticsPeriod(
                $preset,
                $today->modify('first day of last month'),
                $today->modify('first day of this month'),
            ),
            self::PRESET_THIS_YEAR => new StatisticsPeriod(
                $preset,
                $today->modify('first day of January this year'),
                $today->modify('first day of January next year'),
            ),
            default => new StatisticsPeriod(
                self::PRESET_THIS_MONTH,
                $today->modify('first day of this month'),
                $today->modify('first day of next month'),
            ),
        };
    }

    private function resolveCustom(string $from, string $to): ?StatisticsPeriod
    {
        if ($from === '' || $to === '') {
            return null;
        }

        try {
            $start = (new \DateTimeImmutable($from))->setTime(0, 0);
            $endInclusive = (new \DateTimeImmutable($to))->setTime(0, 0);
        } catch (\Exception) {
            return null;
        }

        if ($endInclusive < $start) {
            return null;
        }

        // End of last selected day, exclusive.
        $end = $endInclusive->modify('+1 day');

        return new StatisticsPeriod(self::PRESET_CUSTOM, $start, $end);
    }
}
