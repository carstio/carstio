<?php

namespace App\Tests\Unit\Twig;

use App\Twig\DiscountExtension;
use PHPUnit\Framework\TestCase;

class DiscountExtensionTest extends TestCase
{
    public function testIsDiscountedTreatsZeroAndNullishAsNotDiscounted(): void
    {
        $ext = new DiscountExtension();

        $this->assertFalse($ext->isDiscounted(null));
        $this->assertFalse($ext->isDiscounted(''));
        $this->assertFalse($ext->isDiscounted(0));
        $this->assertFalse($ext->isDiscounted(0.0));
        $this->assertFalse($ext->isDiscounted('0'));
        $this->assertFalse($ext->isDiscounted('0.00'));
    }

    public function testIsDiscountedTreatsPositiveValuesAsDiscounted(): void
    {
        $ext = new DiscountExtension();

        $this->assertTrue($ext->isDiscounted('0.01'));
        $this->assertTrue($ext->isDiscounted('5.50'));
        $this->assertTrue($ext->isDiscounted(12));
        $this->assertTrue($ext->isDiscounted(3.14));
    }

    public function testHasDiscountsReturnsFalseForEmptyOrAllZeroCollection(): void
    {
        $ext = new DiscountExtension();

        $this->assertFalse($ext->hasDiscounts([]));
        $this->assertFalse($ext->hasDiscounts([
            $this->item('0.00'),
            $this->item(null),
            $this->item('0'),
        ]));
    }

    public function testHasDiscountsReturnsTrueWhenAnyItemHasPositiveDiscount(): void
    {
        $ext = new DiscountExtension();

        $this->assertTrue($ext->hasDiscounts([
            $this->item('0.00'),
            $this->item('2.50'),
            $this->item('0.00'),
        ]));
    }

    public function testHasDiscountsIgnoresItemsWithoutGetter(): void
    {
        $ext = new DiscountExtension();

        $this->assertFalse($ext->hasDiscounts([new \stdClass()]));
    }

    private function item(string|float|int|null $discount): object
    {
        return new class($discount) {
            public function __construct(private readonly string|float|int|null $discount)
            {
            }

            public function getDiscountAmount(): string|float|int|null
            {
                return $this->discount;
            }
        };
    }
}
