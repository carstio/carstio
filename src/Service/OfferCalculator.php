<?php

namespace App\Service;

use App\Entity\Sales\Offer;
use App\Enum\OfferItemType;

class OfferCalculator
{
    public function recalculate(Offer $offer): void
    {
        $subtotalLabor = 0.0;
        $subtotalParts = 0.0;
        $totalDiscount = 0.0;
        $totalTax = 0.0;

        foreach ($offer->getItems() as $item) {
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

            if ($item->getType() === OfferItemType::Labor) {
                $subtotalLabor += $lineBase;
            } else {
                $subtotalParts += $lineBase;
            }
        }

        $subtotalRaw = $subtotalLabor + $subtotalParts;
        $afterDiscount = $subtotalRaw - $totalDiscount;
        $totalAmount = $afterDiscount + $totalTax;

        $offer->setSubtotalLabor($this->money($subtotalLabor));
        $offer->setSubtotalParts($this->money($subtotalParts));
        $offer->setDiscountAmount($this->money($totalDiscount));
        $offer->setTaxAmount($this->money($totalTax));
        $offer->setTotalAmount($this->money($totalAmount));
        $offer->setUpdatedAt(new \DateTimeImmutable());
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
