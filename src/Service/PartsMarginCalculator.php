<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Sales\Offer;
use App\Entity\Sales\OfferItem;
use App\Entity\Workshop\WorkOrder;
use App\Entity\Workshop\WorkOrderItem;
use App\Enum\OfferItemType;
use App\Enum\WorkOrderItemType;

/**
 * Computes how much margin the workshop makes on the parts portion of an
 * Offer or WorkOrder. The result is consumed by internal admin views and
 * the statistics dashboard - it must never reach a client-facing PDF.
 *
 * Lines without a recorded cost price are treated as "no cost data" so
 * a missing cost does not silently inflate profit. When at least one
 * part line carries a cost we still compute totals - the UI flags the
 * partial coverage via {@see PartsMargin::$hasCostData}.
 */
final class PartsMarginCalculator
{
    public function forWorkOrder(WorkOrder $workOrder): PartsMargin
    {
        $costTotal = 0.0;
        $sellTotal = 0.0;
        $hasAnyCost = false;

        foreach ($workOrder->getItems() as $item) {
            if (!$this->isPartLine($item)) {
                continue;
            }

            $quantity = $this->toFloat($item->getQuantity());
            $sellTotal += $quantity * $this->toFloat($item->getUnitPrice());

            $cost = $this->resolveCost($item);
            if ($cost !== null) {
                $costTotal += $quantity * $cost;
                $hasAnyCost = true;
            }
        }

        return $this->build($costTotal, $sellTotal, $hasAnyCost);
    }

    public function forOffer(Offer $offer): PartsMargin
    {
        $costTotal = 0.0;
        $sellTotal = 0.0;
        $hasAnyCost = false;

        foreach ($offer->getItems() as $item) {
            if (!$this->isPartLine($item)) {
                continue;
            }

            $quantity = $this->toFloat($item->getQuantity());
            $sellTotal += $quantity * $this->toFloat($item->getUnitPrice());

            $cost = $this->resolveCost($item);
            if ($cost !== null) {
                $costTotal += $quantity * $cost;
                $hasAnyCost = true;
            }
        }

        return $this->build($costTotal, $sellTotal, $hasAnyCost);
    }

    private function build(float $costTotal, float $sellTotal, bool $hasCostData): PartsMargin
    {
        $profit = $sellTotal - $costTotal;

        $margin = null;
        if ($hasCostData && $sellTotal > 0.0) {
            $margin = number_format(($profit / $sellTotal) * 100.0, 1, '.', '');
        }

        return new PartsMargin(
            costTotal: $this->money($costTotal),
            sellTotal: $this->money($sellTotal),
            profit: $this->money($profit),
            marginPercent: $margin,
            hasCostData: $hasCostData,
        );
    }

    private function isPartLine(WorkOrderItem|OfferItem $item): bool
    {
        $type = $item->getType();

        return $type === WorkOrderItemType::Part || $type === OfferItemType::Part;
    }

    /**
     * Cost preference: explicit per-line cost wins over the catalog cost.
     * That way a one-off purchase price recorded on the document stays
     * accurate even if the catalog Part is later updated.
     */
    private function resolveCost(WorkOrderItem|OfferItem $item): ?float
    {
        $explicit = $item->getCostPrice();
        if ($explicit !== null && $explicit !== '') {
            return (float) $explicit;
        }

        $part = $item->getPart();
        if ($part === null) {
            return null;
        }

        $catalogCost = $part->getCostPrice();
        if ($catalogCost === null || $catalogCost === '') {
            return null;
        }

        return (float) $catalogCost;
    }

    private function toFloat(?string $value): float
    {
        if ($value === null || $value === '') {
            return 0.0;
        }

        return (float) $value;
    }

    private function money(float $value): string
    {
        return number_format($value, 2, '.', '');
    }
}
