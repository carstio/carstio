<?php

namespace App\Tests\Unit\Enum;

use App\Enum\Currency;
use PHPUnit\Framework\TestCase;

class CurrencyTest extends TestCase
{
    public function testValuesIncludeExpandedSet(): void
    {
        $values = Currency::values();

        foreach (['EUR', 'USD', 'GBP', 'CHF', 'CAD', 'AUD', 'JPY', 'CNY', 'PLN', 'RON'] as $expected) {
            $this->assertContains($expected, $values, sprintf('Expected currency "%s" to be supported.', $expected));
        }
    }

    public function testBgnIsNoLongerSupported(): void
    {
        $this->assertNotContains('BGN', Currency::values());
        $this->assertNull(Currency::tryFrom('BGN'));
    }

    public function testDefaultIsEur(): void
    {
        $this->assertSame('EUR', Currency::DEFAULT->value);
    }

    public function testChoicesContainEachCurrencyWithSymbol(): void
    {
        $choices = Currency::choices();

        $this->assertContains('EUR', array_values($choices));
        $this->assertContains('PLN', array_values($choices));
        $this->assertContains('RON', array_values($choices));
        $this->assertArrayHasKey('PLN (zł)', $choices);
        $this->assertArrayHasKey('RON (lei)', $choices);
    }
}
