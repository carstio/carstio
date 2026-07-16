<?php

declare(strict_types=1);

namespace App\Service\Workshop;

use App\Entity\Workshop\WorkOrder;

final class WorkOrderPaymentSummarizer
{
    public function summarize(WorkOrder $workOrder): WorkOrderPaymentSummary
    {
        $total = (float) $workOrder->getTotalAmount();
        $paid = (float) $workOrder->getPaidAmount();
        $remaining = max(0.0, $total - $paid);

        return new WorkOrderPaymentSummary(
            totalAmount: $this->money($total),
            paidAmount: $this->money($paid),
            remainingAmount: $this->money($remaining),
            status: $workOrder->getPaymentStatus(),
        );
    }

    private function money(float $value): string
    {
        return number_format($value, 2, '.', '');
    }
}
