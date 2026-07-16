<?php

namespace App\Tests\Unit\Service;

use App\Service\Money\CurrencyFormatter;
use PHPUnit\Framework\TestCase;

class CurrencyFormatterTest extends TestCase
{
    public function testRetiredCodeFallsBackToRawCode(): void
    {
        $formatter = new CurrencyFormatter();

        // BGN is no longer in the Currency enum. Old rows still in the
        // database render with the raw code so they remain readable until
        // their owning record is migrated.
        $this->assertSame('150.00 BGN', $formatter->format('150.00', 'BGN'));
    }

    public function testEurUsesEuroSign(): void
    {
        $formatter = new CurrencyFormatter();

        $this->assertSame('150.00 €', $formatter->format('150.00', 'EUR'));
    }

    public function testUsdUsesDollarSign(): void
    {
        $formatter = new CurrencyFormatter();

        $this->assertSame('150.00 $', $formatter->format('150.00', 'USD'));
    }

    public function testGbpUsesPoundSign(): void
    {
        $formatter = new CurrencyFormatter();

        $this->assertSame('120.00 £', $formatter->format('120.00', 'GBP'));
    }

    public function testJpyUsesYenSign(): void
    {
        $formatter = new CurrencyFormatter();

        $this->assertSame('1500.00 ¥', $formatter->format('1500.00', 'JPY'));
    }

    public function testPlnUsesZlotySymbol(): void
    {
        $formatter = new CurrencyFormatter();

        $this->assertSame('120.00 zł', $formatter->format('120.00', 'PLN'));
    }

    public function testRonUsesLeiSymbol(): void
    {
        $formatter = new CurrencyFormatter();

        $this->assertSame('120.00 lei', $formatter->format('120.00', 'RON'));
    }

    public function testAmbiguousCurrenciesFallBackToIsoCode(): void
    {
        $formatter = new CurrencyFormatter();

        // CHF, CAD, AUD and CNY have no unique single-character symbol -
        // we keep the ISO code so the displayed amount is unambiguous.
        $this->assertSame('120.00 CHF', $formatter->format('120.00', 'CHF'));
        $this->assertSame('120.00 CAD', $formatter->format('120.00', 'CAD'));
        $this->assertSame('120.00 AUD', $formatter->format('120.00', 'AUD'));
        $this->assertSame('120.00 CNY', $formatter->format('120.00', 'CNY'));
    }

    public function testUnknownCodeFallsBackToRawCode(): void
    {
        $formatter = new CurrencyFormatter();

        $this->assertSame('42.00 ZZZ', $formatter->format('42.00', 'ZZZ'));
    }

    public function testNullCurrencyCodeUsesDefaultSymbol(): void
    {
        $formatter = new CurrencyFormatter();

        $this->assertSame('0.00 €', $formatter->format(null, null));
        $this->assertSame('12.34 €', $formatter->format('12.34', ''));
    }

    public function testAcceptsFloatAndIntAmounts(): void
    {
        $formatter = new CurrencyFormatter();

        $this->assertSame('10.00 €', $formatter->format(10, 'EUR'));
        $this->assertSame('10.50 €', $formatter->format(10.5, 'EUR'));
    }

    public function testAlwaysRendersTwoDecimals(): void
    {
        $formatter = new CurrencyFormatter();

        $this->assertSame('7.00 €', $formatter->format('7', 'EUR'));
        $this->assertSame('7.10 €', $formatter->format('7.1', 'EUR'));
    }
}
