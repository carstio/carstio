<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Settings;

use App\Service\Settings\TimezoneChoiceProvider;
use PHPUnit\Framework\TestCase;

class TimezoneChoiceProviderTest extends TestCase
{
    public function testGroupedChoicesContainsCommonIanaIdentifiers(): void
    {
        $choices = (new TimezoneChoiceProvider())->groupedChoices();

        $flat = [];
        foreach ($choices as $entries) {
            foreach ($entries as $value) {
                $flat[] = $value;
            }
        }

        $this->assertContains('Europe/Sofia', $flat);
        $this->assertContains('Europe/Berlin', $flat);
        $this->assertContains('America/New_York', $flat);
        $this->assertContains('UTC', $flat);
    }

    public function testGroupedChoicesUseLabelEqualsValue(): void
    {
        $choices = (new TimezoneChoiceProvider())->groupedChoices();

        foreach ($choices as $entries) {
            foreach ($entries as $label => $value) {
                $this->assertSame(
                    $label,
                    $value,
                    'Each choice should expose the IANA identifier both as its label and stored value.',
                );
            }
        }
    }

    public function testGroupedChoicesSeparateGenericIdentifiers(): void
    {
        $choices = (new TimezoneChoiceProvider())->groupedChoices();

        $this->assertArrayHasKey('settings.regional.timezone_group.generic', $choices);
        $this->assertContains('UTC', array_values($choices['settings.regional.timezone_group.generic']));
        $this->assertArrayHasKey('Europe', $choices);
        $this->assertContains('Europe/Sofia', array_values($choices['Europe']));
    }

    public function testIsValidAcceptsKnownIdentifier(): void
    {
        $provider = new TimezoneChoiceProvider();

        $this->assertTrue($provider->isValid('Europe/Sofia'));
        $this->assertTrue($provider->isValid('UTC'));
    }

    public function testIsValidRejectsUnknownOrEmpty(): void
    {
        $provider = new TimezoneChoiceProvider();

        $this->assertFalse($provider->isValid(null));
        $this->assertFalse($provider->isValid(''));
        $this->assertFalse($provider->isValid('Atlantis/Hidden_City'));
    }
}
