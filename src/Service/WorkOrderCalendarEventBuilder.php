<?php

namespace App\Service;

use App\Entity\Workshop\WorkOrder;
use App\Enum\WorkOrderStatus;
use App\Service\Formatting\CarLabelFormatter;
use App\Service\Formatting\DateFormatter;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class WorkOrderCalendarEventBuilder
{
    private const STATUS_COLORS = [
        'draft' => '#94a3b8',
        'open' => '#2563eb',
        'in_progress' => '#0ea5e9',
        'waiting_parts' => '#f59e0b',
        'waiting_approval' => '#f59e0b',
        'completed' => '#10b981',
        'cancelled' => '#ef4444',
        'closed' => '#475569',
    ];

    public function __construct(
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly DateFormatter $dateFormatter,
        private readonly CarLabelFormatter $carLabelFormatter,
    ) {
    }

    /**
     * @param iterable<WorkOrder> $workOrders
     * @return list<array<string, mixed>>
     */
    public function build(iterable $workOrders): array
    {
        $events = [];
        foreach ($workOrders as $workOrder) {
            if ($workOrder->getScheduledAt() === null) {
                continue;
            }
            $events[] = $this->mapEvent($workOrder);
        }

        return $events;
    }

    public static function colorFor(WorkOrderStatus $status): string
    {
        return self::STATUS_COLORS[$status->value] ?? '#64748b';
    }

    /**
     * @return array<string, mixed>
     */
    private function mapEvent(WorkOrder $workOrder): array
    {
        $scheduledAt = $workOrder->getScheduledAt();
        $clientName = $workOrder->getClient()?->getName() ?? '';
        $vehicleLabel = $this->carLabelFormatter->label($workOrder->getCar());
        $mechanicName = $workOrder->getCreatedBy()?->getFullName();
        $color = self::colorFor($workOrder->getStatus());

        return [
            'id' => (string) $workOrder->getId(),
            'title' => trim($workOrder->getNumber() . ($clientName !== '' ? ' · ' . $clientName : '')),
            'start' => $scheduledAt->format(\DateTimeInterface::ATOM),
            'allDay' => false,
            'url' => $this->urlGenerator->generate('app_work_order_show', ['id' => $workOrder->getId()]),
            'backgroundColor' => $color,
            'borderColor' => $color,
            'textColor' => '#ffffff',
            'extendedProps' => [
                'number' => $workOrder->getNumber(),
                'client' => $clientName,
                'vehicle' => $vehicleLabel,
                'mechanic' => $mechanicName,
                'status' => $workOrder->getStatus()->value,
                'scheduledAt' => $this->dateFormatter->formatDateTime($scheduledAt),
            ],
        ];
    }
}
