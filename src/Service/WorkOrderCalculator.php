<?php

namespace App\Service;

use App\Entity\Workshop\WorkOrder;
use App\Enum\PaymentStatus;
use App\Enum\WorkOrderItemType;

class WorkOrderCalculator
{
    public function recalculate(WorkOrder $workOrder): void
    {
        $subtotalLabor = 0.0;
        $subtotalParts = 0.0;
        $totalDiscount = 0.0;
        $totalTax = 0.0;

        foreach ($workOrder->getItems() as $item) {
            $quantity = $this->toFloat($item->getQuantity());
            $unitPrice = $this->toFloat($item->getUnitPrice());
            $discount = $this->toFloat($item->getDiscountAmount());
            $taxRate = $this->toFloat($item->getTaxRate());

            $lineBase = $quantity * $unitPrice;
            $lineAfterDiscount = $lineBase - $discount;
            $lineTax = $lineAfterDiscount * ($taxRate / 100);
            $lineTotal = $lineAfterDiscount + $lineTax;

            $item->setLineTotal($this->money($lineTotal));

            $totalDiscount += $discount;
            $totalTax += $lineTax;

            if ($item->getType() === WorkOrderItemType::Labor) {
                $subtotalLabor += $lineBase;
            } else {
                $subtotalParts += $lineBase;
            }
        }

        $subtotalRaw = $subtotalLabor + $subtotalParts;
        $afterDiscount = $subtotalRaw - $totalDiscount;
        $totalAmount = $afterDiscount + $totalTax;

        $workOrder->setSubtotalLabor($this->money($subtotalLabor));
        $workOrder->setSubtotalParts($this->money($subtotalParts));
        $workOrder->setDiscountAmount($this->money($totalDiscount));
        $workOrder->setTaxAmount($this->money($totalTax));
        $workOrder->setTotalAmount($this->money($totalAmount));

        $paidAmount = 0.0;
        foreach ($workOrder->getPayments() as $payment) {
            if ($payment->getStatus() === PaymentStatus::Paid) {
                $paidAmount += $this->toFloat($payment->getAmount());
            }
        }

        $workOrder->setPaidAmount($this->money($paidAmount));

        // Compared in whole cents, the way both amounts are stored and shown.
        // The raw sums carry fractions of a cent (10.02 + 20% tax is 12.024)
        // and binary rounding noise (0.1 + 0.1 + 0.1 is not 0.3): compared as
        // they are, someone who paid exactly the amount on the invoice would
        // remain "partially paid" for ever.
        $paidCents = (int) round($paidAmount * 100);
        $totalCents = (int) round($totalAmount * 100);

        if ($paidCents <= 0) {
            $workOrder->setPaymentStatus(PaymentStatus::Pending);
        } elseif ($paidCents >= $totalCents) {
            $workOrder->setPaymentStatus(PaymentStatus::Paid);
        } else {
            $workOrder->setPaymentStatus(PaymentStatus::PartiallyPaid);
        }

        $workOrder->setUpdatedAt(new \DateTimeImmutable());
    }

    private function money(float $value): string
    {
        return number_format($value, 2, '.', '');
    }

    private function toFloat(?string $value): float
    {
        if ($value === null || $value === '') {
            return 0.0;
        }

        return (float) $value;
    }
}
