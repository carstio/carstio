<?php

declare(strict_types=1);

namespace App\Service\Statistics\View;

final class TopProfitablePartStatsView
{
    public function __construct(
        public readonly string $name,
        public readonly string $quantity,
        public readonly string $revenue,
        public readonly string $cost,
        public readonly string $profit,
        public readonly bool $hasCostData,
    ) {
    }
}
