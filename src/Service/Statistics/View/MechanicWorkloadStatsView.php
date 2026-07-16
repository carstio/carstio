<?php

declare(strict_types=1);

namespace App\Service\Statistics\View;

/**
 * Mechanic workload row.
 *
 * "Mechanic" maps to {@see \App\Entity\Workshop\WorkOrder::$createdBy} - the
 * domain has no separate "assigned to" field yet, so the creator is the
 * best available proxy. When that changes, only this view model and the
 * underlying repository query need to follow.
 */
final class MechanicWorkloadStatsView
{
    public function __construct(
        public readonly int $userId,
        public readonly string $fullName,
        public readonly int $activeWorkOrders,
        public readonly int $completedInPeriod,
        public readonly int $totalAssigned,
    ) {
    }
}
