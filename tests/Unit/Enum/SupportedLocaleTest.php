<?php

namespace App\Tests\Unit\Enum;

use App\Enum\SupportedLocale;
use PHPUnit\Framework\TestCase;

class SupportedLocaleTest extends TestCase
{
    public function testDefaultIsEnglish(): void
    {
        $this->assertSame('en', SupportedLocale::DEFAULT->value);
    }

    public function testValuesContainExpectedLocales(): void
    {
        $values = SupportedLocale::values();

        foreach (['en', 'bg', 'de', 'es', 'fr'] as $expected) {
            $this->assertContains($expected, $values, sprintf('Expected locale "%s" to be supported.', $expected));
        }
    }

    public function testIsSupportedAcceptsKnownAndRejectsUnknown(): void
    {
        $this->assertTrue(SupportedLocale::isSupported('en'));
        $this->assertTrue(SupportedLocale::isSupported('bg'));
        $this->assertFalse(SupportedLocale::isSupported('zz'));
        $this->assertFalse(SupportedLocale::isSupported(''));
        $this->assertFalse(SupportedLocale::isSupported(null));
    }

    public function testChoicesUseNativeNamesAsLabels(): void
    {
        $choices = SupportedLocale::choices();

        $this->assertSame('en', $choices['English']);
        $this->assertSame('bg', $choices['Български']);
        $this->assertSame('de', $choices['Deutsch']);
        $this->assertSame('es', $choices['Español']);
        $this->assertSame('fr', $choices['Français']);
    }
}
