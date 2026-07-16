<?php

namespace App\Tests\Unit\Service;

use App\Entity\Sales\Offer;
use App\Entity\Sales\OfferItem;
use App\Enum\OfferItemType;
use App\Service\OfferCalculator;
use PHPUnit\Framework\TestCase;

class OfferCalculatorTest extends TestCase
{
    private OfferCalculator $calculator;

    protected function setUp(): void
    {
        $this->calculator = new OfferCalculator();
    }

    public function testEmptyOffer(): void
    {
        $offer = new Offer();
        $this->calculator->recalculate($offer);

        $this->assertSame('0.00', $offer->getSubtotalLabor());
        $this->assertSame('0.00', $offer->getSubtotalParts());
        $this->assertSame('0.00', $offer->getDiscountAmount());
        $this->assertSame('0.00', $offer->getTaxAmount());
        $this->assertSame('0.00', $offer->getTotalAmount());
    }

    public function testMixedItemsCalculation(): void
    {
        $offer = new Offer();

        $labor = new OfferItem();
        $labor->setType(OfferItemType::Labor);
        $labor->setName('Brake service');
        $labor->setQuantity('1.00');
        $labor->setUnitPrice('60.00');
        $offer->addItem($labor);

        $part = new OfferItem();
        $part->setType(OfferItemType::Part);
        $part->setName('Brake pads');
        $part->setQuantity('1.00');
        $part->setUnitPrice('55.00');
        $offer->addItem($part);

        $misc = new OfferItem();
        $misc->setType(OfferItemType::Misc);
        $misc->setName('Disposal fee');
        $misc->setQuantity('1.00');
        $misc->setUnitPrice('5.00');
        $offer->addItem($misc);

        $this->calculator->recalculate($offer);

        $this->assertSame('60.00', $offer->getSubtotalLabor());
        $this->assertSame('60.00', $offer->getSubtotalParts());
        $this->assertSame('120.00', $offer->getTotalAmount());
    }

    public function testDiscountAndTaxCombined(): void
    {
        $offer = new Offer();

        $item = new OfferItem();
        $item->setType(OfferItemType::Part);
        $item->setName('Part');
        $item->setQuantity('1.00');
        $item->setUnitPrice('100.00');
        $item->setDiscountAmount('10.00');
        $item->setTaxRate('20.00');
        $offer->addItem($item);

        $this->calculator->recalculate($offer);

        $this->assertSame('10.00', $offer->getDiscountAmount());
        $this->assertSame('18.00', $offer->getTaxAmount());
        $this->assertSame('108.00', $offer->getTotalAmount());
        $this->assertSame('108.00', $item->getLineTotal());
    }

    public function testZeroDiscountAndTaxBehaveLikeOmitted(): void
    {
        $offer = new Offer();

        $item = new OfferItem();
        $item->setType(OfferItemType::Labor);
        $item->setName('Service');
        $item->setQuantity('1.00');
        $item->setUnitPrice('100.00');
        $item->setDiscountAmount('0.00');
        $item->setTaxRate('0.00');
        $offer->addItem($item);

        $this->calculator->recalculate($offer);

        $this->assertSame('0.00', $offer->getDiscountAmount());
        $this->assertSame('0.00', $offer->getTaxAmount());
        $this->assertSame('100.00', $offer->getTotalAmount());
        $this->assertSame('100.00', $item->getLineTotal());
    }

    public function testCalculatorToleratesEmptyDiscountAndTaxRateStrings(): void
    {
        $offer = new Offer();

        $item = new OfferItem();
        $item->setType(OfferItemType::Labor);
        $item->setName('Service');
        $item->setQuantity('1.00');
        $item->setUnitPrice('100.00');
        $item->setDiscountAmount('');
        $item->setTaxRate('');
        $offer->addItem($item);

        $this->calculator->recalculate($offer);

        $this->assertSame('0.00', $offer->getDiscountAmount());
        $this->assertSame('0.00', $offer->getTaxAmount());
        $this->assertSame('100.00', $offer->getTotalAmount());
        $this->assertSame('100.00', $item->getLineTotal());
    }

    public function testOnlyDiscountWithoutTaxCalculatesCorrectly(): void
    {
        $offer = new Offer();

        $item = new OfferItem();
        $item->setType(OfferItemType::Part);
        $item->setName('Filter');
        $item->setQuantity('1.00');
        $item->setUnitPrice('50.00');
        $item->setDiscountAmount('5.00');
        $offer->addItem($item);

        $this->calculator->recalculate($offer);

        $this->assertSame('5.00', $offer->getDiscountAmount());
        $this->assertSame('0.00', $offer->getTaxAmount());
        $this->assertSame('45.00', $offer->getTotalAmount());
        $this->assertSame('45.00', $item->getLineTotal());
    }

    public function testOnlyTaxWithoutDiscountCalculatesCorrectly(): void
    {
        $offer = new Offer();

        $item = new OfferItem();
        $item->setType(OfferItemType::Part);
        $item->setName('Filter');
        $item->setQuantity('1.00');
        $item->setUnitPrice('50.00');
        $item->setTaxRate('10.00');
        $offer->addItem($item);

        $this->calculator->recalculate($offer);

        $this->assertSame('0.00', $offer->getDiscountAmount());
        $this->assertSame('5.00', $offer->getTaxAmount());
        $this->assertSame('55.00', $offer->getTotalAmount());
        $this->assertSame('55.00', $item->getLineTotal());
    }
}
