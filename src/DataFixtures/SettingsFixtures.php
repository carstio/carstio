<?php

namespace App\DataFixtures;

use App\Entity\Settings\Setting;
use App\Enum\Currency;
use App\Enum\SettingType;
use App\Enum\SmtpEncryption;
use App\Enum\SupportedLocale;
use App\Settings\SettingsRegistry;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

/**
 * Seed practical defaults for local development. Each entry references a
 * registry key (no magic strings) and we ask the registry for the type so
 * fixture rows match what SettingsManager would write at runtime.
 */
class SettingsFixtures extends Fixture
{
    public function __construct(private readonly SettingsRegistry $registry)
    {
    }

    public function load(ObjectManager $manager): void
    {
        $values = [
            // Company
            [SettingsRegistry::SECTION_COMPANY, SettingsRegistry::KEY_COMPANY_NAME, 'Carstio Workshop Demo'],
            [SettingsRegistry::SECTION_COMPANY, SettingsRegistry::KEY_COMPANY_ID,   '123456789'],
            [SettingsRegistry::SECTION_COMPANY, SettingsRegistry::KEY_VAT_NUMBER,   'BG123456789'],
            [SettingsRegistry::SECTION_COMPANY, SettingsRegistry::KEY_ADDRESS,      'Sample Street 12'],
            [SettingsRegistry::SECTION_COMPANY, SettingsRegistry::KEY_CITY,         'Sofia'],
            [SettingsRegistry::SECTION_COMPANY, SettingsRegistry::KEY_COUNTRY,      'Bulgaria'],
            [SettingsRegistry::SECTION_COMPANY, SettingsRegistry::KEY_PHONE,        '+359 2 000 0000'],
            [SettingsRegistry::SECTION_COMPANY, SettingsRegistry::KEY_EMAIL,        'hello@carstio.local'],
            [SettingsRegistry::SECTION_COMPANY, SettingsRegistry::KEY_WEBSITE,      'https://carstio.local'],

            // Regional
            [SettingsRegistry::SECTION_REGIONAL, SettingsRegistry::KEY_DEFAULT_LOCALE, SupportedLocale::DEFAULT->value],
            [SettingsRegistry::SECTION_REGIONAL, SettingsRegistry::KEY_TIMEZONE,       'Europe/Sofia'],
            [SettingsRegistry::SECTION_REGIONAL, SettingsRegistry::KEY_DATE_FORMAT,    'Y-m-d'],
            [SettingsRegistry::SECTION_REGIONAL, SettingsRegistry::KEY_TIME_FORMAT,    'H:i'],
            [SettingsRegistry::SECTION_REGIONAL, SettingsRegistry::KEY_MILEAGE_UNIT,   'km'],

            // Currency / tax
            [SettingsRegistry::SECTION_CURRENCY, SettingsRegistry::KEY_DEFAULT_CURRENCY_CODE, Currency::DEFAULT->value],
            [SettingsRegistry::SECTION_CURRENCY, SettingsRegistry::KEY_DEFAULT_TAX_RATE,      '20.00'],

            // Documents
            [SettingsRegistry::SECTION_DOCUMENTS, SettingsRegistry::KEY_OFFER_NUMBER_PREFIX,        'OF'],
            [SettingsRegistry::SECTION_DOCUMENTS, SettingsRegistry::KEY_WORK_ORDER_NUMBER_PREFIX,   'WO'],
            [SettingsRegistry::SECTION_DOCUMENTS, SettingsRegistry::KEY_INVOICE_NUMBER_PREFIX,      'INV'],
            [SettingsRegistry::SECTION_DOCUMENTS, SettingsRegistry::KEY_DEFAULT_OFFER_VALIDITY_DAYS, '14'],
            [SettingsRegistry::SECTION_DOCUMENTS, SettingsRegistry::KEY_DOCUMENT_FOOTER_TEXT,
                'Thank you for choosing Carstio.'],

            // Email / SMTP - clearly fake demo values, no password.
            [SettingsRegistry::SECTION_EMAIL, SettingsRegistry::KEY_SMTP_HOST,       'smtp.example.com'],
            [SettingsRegistry::SECTION_EMAIL, SettingsRegistry::KEY_SMTP_PORT,       '587'],
            [SettingsRegistry::SECTION_EMAIL, SettingsRegistry::KEY_SMTP_ENCRYPTION, SmtpEncryption::Tls->value],
            [SettingsRegistry::SECTION_EMAIL, SettingsRegistry::KEY_SMTP_USERNAME,   'demo@carstio.local'],
            [SettingsRegistry::SECTION_EMAIL, SettingsRegistry::KEY_SMTP_FROM_EMAIL, 'no-reply@carstio.local'],
            [SettingsRegistry::SECTION_EMAIL, SettingsRegistry::KEY_SMTP_FROM_NAME,  'Carstio Workshop'],
        ];

        foreach ($values as [$section, $key, $value]) {
            $definition = $this->registry->get($section, $key);
            $setting = new Setting($section, $key, $definition->type->value);
            $setting->setSortOrder($definition->sortOrder);
            $setting->setValue($value);

            $manager->persist($setting);
        }

        $manager->flush();
    }
}
