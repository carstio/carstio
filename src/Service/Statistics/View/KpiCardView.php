<?php

declare(strict_types=1);

namespace App\Service\Statistics\View;

/**
 * View model for one KPI card on the statistics dashboard.
 *
 * `value` is rendered verbatim - money formatting, percent suffix, and
 * counts are applied by the service so templates stay logic-free.
 */
final class KpiCardView
{
    public function __construct(
        public readonly string $labelKey,
        public readonly string $value,
        public readonly string $icon,
        public readonly string $iconVariant = 'primary',
        public readonly ?string $hint = null,
    ) {
    }
}
