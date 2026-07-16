<?php

namespace App\Tests\Unit\Settings;

use App\Enum\SettingType;
use App\Settings\SettingsRegistry;
use PHPUnit\Framework\TestCase;

class SettingsRegistryTest extends TestCase
{
    public function testHasReturnsTrueForKnownKey(): void
    {
        $registry = new SettingsRegistry();

        $this->assertTrue($registry->has(
            SettingsRegistry::SECTION_COMPANY,
            SettingsRegistry::KEY_COMPANY_NAME,
        ));
    }

    public function testGetReturnsTypedDefinition(): void
    {
        $registry = new SettingsRegistry();
        $definition = $registry->get(
            SettingsRegistry::SECTION_DOCUMENTS,
            SettingsRegistry::KEY_DEFAULT_OFFER_VALIDITY_DAYS,
        );

        $this->assertSame(SettingType::Integer, $definition->type);
        $this->assertSame(14, $definition->default);
    }

    public function testUnknownKeyThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        (new SettingsRegistry())->get('company', 'nope');
    }

    public function testBySectionIsOrderedBySortOrder(): void
    {
        $definitions = (new SettingsRegistry())->bySection(SettingsRegistry::SECTION_COMPANY);

        $sortOrders = array_map(fn ($d) => $d->sortOrder, $definitions);
        $sorted = $sortOrders;
        sort($sorted);

        $this->assertSame($sorted, $sortOrders, 'Definitions should be returned in registration order which matches sort order.');
    }

    public function testSecretFlagFollowsType(): void
    {
        $registry = new SettingsRegistry();

        $this->assertTrue(
            $registry->get(SettingsRegistry::SECTION_EMAIL, SettingsRegistry::KEY_SMTP_PASSWORD)->isSecret(),
        );
        $this->assertFalse(
            $registry->get(SettingsRegistry::SECTION_EMAIL, SettingsRegistry::KEY_SMTP_HOST)->isSecret(),
        );
    }

    public function testTimezoneIsExposedAsAutocompleteChoice(): void
    {
        $registry = new SettingsRegistry();
        $definition = $registry->get(SettingsRegistry::SECTION_REGIONAL, SettingsRegistry::KEY_TIMEZONE);

        $this->assertSame(SettingType::Choice, $definition->type);
        $this->assertTrue($definition->autocomplete);
        $this->assertNotNull($definition->choices);

        // Choices come grouped - flatten and assert a few well-known zones exist.
        $flat = [];
        foreach ($definition->choices as $entries) {
            foreach ($entries as $value) {
                $flat[] = $value;
            }
        }
        $this->assertContains('Europe/Sofia', $flat);
        $this->assertContains('UTC', $flat);
    }
}
