<?php

declare(strict_types=1);

namespace App\Service\Workshop;

use App\Enum\PaymentStatus;

final readonly class WorkOrderPaymentSummary
{
    public function __construct(
        public string $totalAmount,
        public string $paidAmount,
        public string $remainingAmount,
        public PaymentStatus $status,
    ) {
    }
}
