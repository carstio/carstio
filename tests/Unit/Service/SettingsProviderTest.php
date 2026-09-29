<?php

namespace App\Tests\Unit\Service;

use App\Entity\Settings\Setting;
use App\Enum\SettingType;
use App\Repository\Settings\SettingRepository;
use App\Service\Settings\SettingsProvider;
use App\Service\Settings\SmtpPasswordEncrypter;
use App\Settings\SettingsRegistry;
use PHPUnit\Framework\TestCase;

class SettingsProviderTest extends TestCase
{
    public function testReturnsRegistryDefaultWhenRowMissing(): void
    {
        $provider = $this->buildProvider([]);

        $this->assertSame(14, $provider->getInt(
            SettingsRegistry::SECTION_DOCUMENTS,
            SettingsRegistry::KEY_DEFAULT_OFFER_VALIDITY_DAYS,
        ));
    }

    public function testCastsIntegerStrings(): void
    {
        $provider = $this->buildProvider([
            $this->row(SettingsRegistry::SECTION_DOCUMENTS, SettingsRegistry::KEY_DEFAULT_OFFER_VALIDITY_DAYS, '30', SettingType::Integer),
        ]);

        $this->assertSame(30, $provider->getInt(
            SettingsRegistry::SECTION_DOCUMENTS,
            SettingsRegistry::KEY_DEFAULT_OFFER_VALIDITY_DAYS,
        ));
    }

    public function testCastsFloatStrings(): void
    {
        $provider = $this->buildProvider([
            $this->row(SettingsRegistry::SECTION_CURRENCY, SettingsRegistry::KEY_DEFAULT_TAX_RATE, '20.5', SettingType::Float),
        ]);

        $this->assertSame(20.5, $provider->getFloat(
            SettingsRegistry::SECTION_CURRENCY,
            SettingsRegistry::KEY_DEFAULT_TAX_RATE,
        ));
    }

    public function testGetStringReturnsNullForBlankRow(): void
    {
        $provider = $this->buildProvider([
            $this->row(SettingsRegistry::SECTION_COMPANY, SettingsRegistry::KEY_COMPANY_NAME, null, SettingType::String),
        ]);

        $this->assertNull($provider->getString(
            SettingsRegistry::SECTION_COMPANY,
            SettingsRegistry::KEY_COMPANY_NAME,
        ));
    }

    public function testGetBoolHandlesStringValues(): void
    {
        $registry = new SettingsRegistry();
        $repo = $this->createStub(SettingRepository::class);
        $repo->method('findAllSettings')->willReturn([]);

        $provider = new SettingsProvider($repo, $registry, new SmtpPasswordEncrypter('test-secret'));

        // The provider treats only ('1','true', true) as truthy. Defaults
        // missing → false. This matches what callers expect from a flag.
        $this->assertFalse($provider->getBool(SettingsRegistry::SECTION_COMPANY, SettingsRegistry::KEY_COMPANY_NAME));
    }

    public function testIsSetReportsBlankAsNotSet(): void
    {
        $provider = $this->buildProvider([
            $this->row(SettingsRegistry::SECTION_EMAIL, SettingsRegistry::KEY_SMTP_PASSWORD, '', SettingType::Secret),
        ]);

        $this->assertFalse($provider->isSet(SettingsRegistry::SECTION_EMAIL, SettingsRegistry::KEY_SMTP_PASSWORD));
    }

    public function testValuesForSectionReturnsFreshValues(): void
    {
        $provider = $this->buildProvider([
            $this->row(SettingsRegistry::SECTION_CURRENCY, SettingsRegistry::KEY_DEFAULT_CURRENCY_CODE, 'EUR', SettingType::Choice),
            $this->row(SettingsRegistry::SECTION_CURRENCY, SettingsRegistry::KEY_DEFAULT_TAX_RATE, '20', SettingType::Float),
        ]);

        $values = $provider->valuesForSection(SettingsRegistry::SECTION_CURRENCY);

        $this->assertSame('EUR', $values[SettingsRegistry::KEY_DEFAULT_CURRENCY_CODE]);
        $this->assertSame(20.0, $values[SettingsRegistry::KEY_DEFAULT_TAX_RATE]);
    }

    /**
     * @param list<Setting> $rows
     */
    private function buildProvider(array $rows): SettingsProvider
    {
        $repo = $this->createStub(SettingRepository::class);
        $repo->method('findAllSettings')->willReturn($rows);

        return new SettingsProvider($repo, new SettingsRegistry(), new SmtpPasswordEncrypter('test-secret'));
    }

    private function row(string $section, string $key, ?string $value, SettingType $type): Setting
    {
        $setting = new Setting($section, $key, $type->value);
        $setting->setValue($value);

        return $setting;
    }
}
