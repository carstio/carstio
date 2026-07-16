<?php

declare(strict_types=1);

namespace App\Service\Statistics\View;

/**
 * Internal-only view exposing parts revenue / cost / profit / margin
 * for the dashboard period.
 *
 * Margin is null when revenue is zero (no division by zero).
 * `hasCostData` is false when no part line in the period carries a cost,
 * letting the template show a hint instead of misleading "100% margin".
 */
final class PartsProfitStatsView
{
    public function __construct(
        public readonly string $revenue,
        public readonly string $cost,
        public readonly string $profit,
        public readonly ?string $marginPercent,
        public readonly bool $hasCostData,
    ) {
    }
}
