<?php

declare(strict_types=1);

namespace App\Service\Workshop;

use App\Entity\Workshop\WorkOrder;
use App\Enum\WorkOrderStatus;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Applies a new status to a work order and stamps the related lifecycle
 * timestamps (startedAt / completedAt / closedAt). Owns the rule that a
 * work order's startedAt is set the first time it transitions to
 * InProgress and is preserved on later transitions.
 */
final class WorkOrderStatusUpdater
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function update(WorkOrder $workOrder, WorkOrderStatus $newStatus): void
    {
        $now = new \DateTimeImmutable();

        $workOrder->setStatus($newStatus);
        $workOrder->setUpdatedAt($now);

        match ($newStatus) {
            WorkOrderStatus::InProgress => $workOrder->setStartedAt($workOrder->getStartedAt() ?? $now),
            WorkOrderStatus::Completed => $workOrder->setCompletedAt($now),
            WorkOrderStatus::Closed => $workOrder->setClosedAt($now),
            default => null,
        };

        $this->entityManager->flush();
    }
}
