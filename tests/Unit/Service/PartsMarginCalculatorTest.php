<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Entity\Part\Part;
use App\Entity\Sales\Offer;
use App\Entity\Sales\OfferItem;
use App\Entity\Workshop\WorkOrder;
use App\Entity\Workshop\WorkOrderItem;
use App\Enum\OfferItemType;
use App\Enum\WorkOrderItemType;
use App\Service\PartsMarginCalculator;
use PHPUnit\Framework\TestCase;

class PartsMarginCalculatorTest extends TestCase
{
    private PartsMarginCalculator $calculator;

    protected function setUp(): void
    {
        $this->calculator = new PartsMarginCalculator();
    }

    public function testEmptyWorkOrderReturnsZeroAndNoCostFlag(): void
    {
        $margin = $this->calculator->forWorkOrder(new WorkOrder());

        $this->assertSame('0.00', $margin->costTotal);
        $this->assertSame('0.00', $margin->sellTotal);
        $this->assertSame('0.00', $margin->profit);
        $this->assertNull($margin->marginPercent);
        $this->assertFalse($margin->hasCostData);
    }

    public function testLaborLinesDoNotContributeToPartsMargin(): void
    {
        $wo = new WorkOrder();
        $labor = (new WorkOrderItem())
            ->setType(WorkOrderItemType::Labor)
            ->setName('Service')
            ->setQuantity('1.00')
            ->setUnitPrice('60.00')
            ->setCostPrice('30.00'); // ignored - labor isn't part
        $wo->addItem($labor);

        $margin = $this->calculator->forWorkOrder($wo);

        $this->assertSame('0.00', $margin->sellTotal);
        $this->assertFalse($margin->hasCostData);
    }

    public function testExplicitItemCostBeatsCatalogCost(): void
    {
        $part = (new Part())->setName('Brake pad')->setCostPrice('22.00')->setSellPrice('55.00');

        $wo = new WorkOrder();
        $item = (new WorkOrderItem())
            ->setType(WorkOrderItemType::Part)
            ->setName('Brake pad')
            ->setPart($part)
            ->setQuantity('2.00')
            ->setUnitPrice('55.00')
            ->setCostPrice('25.00'); // explicit per-line cost wins
        $wo->addItem($item);

        $margin = $this->calculator->forWorkOrder($wo);

        $this->assertSame('50.00', $margin->costTotal);
        $this->assertSame('110.00', $margin->sellTotal);
        $this->assertSame('60.00', $margin->profit);
        $this->assertSame('54.5', $margin->marginPercent);
        $this->assertTrue($margin->hasCostData);
    }

    public function testCatalogCostIsUsedWhenItemHasNone(): void
    {
        $part = (new Part())->setName('Spark Plug')->setCostPrice('4.00')->setSellPrice('9.50');

        $wo = new WorkOrder();
        $item = (new WorkOrderItem())
            ->setType(WorkOrderItemType::Part)
            ->setName('Spark Plug')
            ->setPart($part)
            ->setQuantity('4.00')
            ->setUnitPrice('9.50');
        $wo->addItem($item);

        $margin = $this->calculator->forWorkOrder($wo);

        $this->assertSame('16.00', $margin->costTotal);
        $this->assertSame('38.00', $margin->sellTotal);
        $this->assertSame('22.00', $margin->profit);
        $this->assertTrue($margin->hasCostData);
    }

    public function testFreeFormPartWithoutCostStillRevenueAndFlagsMissingCost(): void
    {
        $wo = new WorkOrder();
        $item = (new WorkOrderItem())
            ->setType(WorkOrderItemType::Part)
            ->setName('Misc bolt')
            ->setQuantity('5.00')
            ->setUnitPrice('1.20');
        $wo->addItem($item);

        $margin = $this->calculator->forWorkOrder($wo);

        $this->assertSame('0.00', $margin->costTotal);
        $this->assertSame('6.00', $margin->sellTotal);
        $this->assertSame('6.00', $margin->profit);
        $this->assertNull($margin->marginPercent, 'Without cost data we must not show a margin percent.');
        $this->assertFalse($margin->hasCostData);
    }

    public function testZeroSellTotalDoesNotProduceDivisionByZero(): void
    {
        $wo = new WorkOrder();
        $item = (new WorkOrderItem())
            ->setType(WorkOrderItemType::Part)
            ->setName('Free part')
            ->setQuantity('1.00')
            ->setUnitPrice('0.00')
            ->setCostPrice('0.00');
        $wo->addItem($item);

        $margin = $this->calculator->forWorkOrder($wo);

        $this->assertSame('0.00', $margin->sellTotal);
        $this->assertSame('0.00', $margin->costTotal);
        $this->assertSame('0.00', $margin->profit);
        $this->assertNull($margin->marginPercent);
        $this->assertTrue($margin->hasCostData);
    }

    public function testEmptyCostStringIsTreatedAsMissingCost(): void
    {
        $wo = new WorkOrder();
        $item = (new WorkOrderItem())
            ->setType(WorkOrderItemType::Part)
            ->setName('Filter')
            ->setQuantity('1.00')
            ->setUnitPrice('10.00')
            ->setCostPrice(''); // edge case from form binding
        $wo->addItem($item);

        $margin = $this->calculator->forWorkOrder($wo);

        $this->assertSame('0.00', $margin->costTotal);
        $this->assertFalse($margin->hasCostData);
    }

    public function testForOfferComputesMarginIdenticallyToWorkOrder(): void
    {
        $offer = new Offer();
        $part = (new Part())->setName('Filter')->setCostPrice('3.50')->setSellPrice('8.00');

        $item = (new OfferItem())
            ->setType(OfferItemType::Part)
            ->setName('Filter')
            ->setPart($part)
            ->setQuantity('2.00')
            ->setUnitPrice('8.00');
        $offer->addItem($item);

        $margin = $this->calculator->forOffer($offer);

        $this->assertSame('7.00', $margin->costTotal);
        $this->assertSame('16.00', $margin->sellTotal);
        $this->assertSame('9.00', $margin->profit);
        $this->assertSame('56.3', $margin->marginPercent);
    }
}
