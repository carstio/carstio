<?php

declare(strict_types=1);

namespace App\Tests\Unit\Entity;

use App\Entity\Part\Part;
use PHPUnit\Framework\TestCase;

class PartTest extends TestCase
{
    public function testPartCanBeCreatedWithJustAName(): void
    {
        $part = new Part();
        $part->setName('Generic resold part');

        $this->assertSame('Generic resold part', $part->getName());
        $this->assertNull($part->getSku());
        $this->assertNull($part->getPartNumber());
        $this->assertNull($part->getCostPrice());
        $this->assertNull($part->getSellPrice());
        $this->assertTrue($part->isActive());
    }

    public function testCostAndSellPricesAreOptional(): void
    {
        $part = new Part();
        $part->setName('Oil Filter');
        $part->setCostPrice('3.50');
        $part->setSellPrice('8.00');

        $this->assertSame('3.50', $part->getCostPrice());
        $this->assertSame('8.00', $part->getSellPrice());

        $part->setCostPrice(null);
        $part->setSellPrice(null);

        $this->assertNull($part->getCostPrice());
        $this->assertNull($part->getSellPrice());
    }
}
