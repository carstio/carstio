<?php

declare(strict_types=1);

namespace App\Service\Statistics\View;

final class TopClientStatsView
{
    public function __construct(
        public readonly int $clientId,
        public readonly string $clientName,
        public readonly int $workOrderCount,
        public readonly string $totalRevenue,
        public readonly string $unpaidAmount,
    ) {
    }
}
