<?php

namespace App\Settings;

use App\Entity\Settings\RegionalDefaults;
use App\Enum\Currency;
use App\Enum\MileageUnit;
use App\Enum\SettingType;
use App\Enum\SmtpEncryption;
use App\Enum\SupportedLocale;
use App\Service\Settings\TimezoneChoiceProvider;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Single source of truth for every setting the application knows.
 *
 * To add a new setting:
 *   1. add a constant for its key (optional but encouraged),
 *   2. add a {@see SettingDefinition} entry below in the right section,
 *   3. read it via {@see \App\Service\Settings\SettingsProvider}.
 *
 * No migration is needed - the database stores whatever the registry
 * writes, and unknown rows are ignored on read.
 */
class SettingsRegistry
{
    public const SECTION_COMPANY = 'company';
    public const SECTION_REGIONAL = 'regional';
    public const SECTION_CURRENCY = 'currency';
    public const SECTION_DOCUMENTS = 'documents';
    public const SECTION_EMAIL = 'email';

    public const KEY_COMPANY_NAME = 'company_name';
    public const KEY_COMPANY_ID = 'company_id';
    public const KEY_VAT_NUMBER = 'vat_number';
    public const KEY_LOGO_PATH = 'logo_path';
    public const KEY_ADDRESS = 'address';
    public const KEY_CITY = 'city';
    public const KEY_COUNTRY = 'country';
    public const KEY_PHONE = 'phone';
    public const KEY_EMAIL = 'email';
    public const KEY_WEBSITE = 'website';

    public const KEY_DEFAULT_LOCALE = 'default_locale';
    public const KEY_TIMEZONE = 'timezone';
    public const KEY_DATE_FORMAT = 'date_format';
    public const KEY_TIME_FORMAT = 'time_format';
    public const KEY_MILEAGE_UNIT = 'mileage_unit';

    public const KEY_DEFAULT_CURRENCY_CODE = 'default_currency_code';
    public const KEY_DEFAULT_TAX_RATE = 'default_tax_rate';

    public const KEY_OFFER_NUMBER_PREFIX = 'offer_number_prefix';
    public const KEY_WORK_ORDER_NUMBER_PREFIX = 'work_order_number_prefix';
    public const KEY_INVOICE_NUMBER_PREFIX = 'invoice_number_prefix';
    public const KEY_DEFAULT_OFFER_VALIDITY_DAYS = 'default_offer_validity_days';
    public const KEY_DOCUMENT_FOOTER_TEXT = 'document_footer_text';

    public const KEY_SMTP_HOST = 'smtp_host';
    public const KEY_SMTP_PORT = 'smtp_port';
    public const KEY_SMTP_ENCRYPTION = 'smtp_encryption';
    public const KEY_SMTP_USERNAME = 'smtp_username';
    public const KEY_SMTP_PASSWORD = 'smtp_password';
    public const KEY_SMTP_FROM_EMAIL = 'smtp_from_email';
    public const KEY_SMTP_FROM_NAME = 'smtp_from_name';

    /** @var array<string, SettingDefinition> indexed by "section.key" */
    private array $definitions = [];

    /** @var array<string, list<SettingDefinition>> grouped by section, sort order preserved */
    private array $bySection = [];

    private readonly TimezoneChoiceProvider $timezoneChoiceProvider;

    public function __construct(?TimezoneChoiceProvider $timezoneChoiceProvider = null)
    {
        $this->timezoneChoiceProvider = $timezoneChoiceProvider ?? new TimezoneChoiceProvider();

        foreach ($this->buildDefinitions() as $definition) {
            $this->definitions[$definition->id()] = $definition;
            $this->bySection[$definition->section][] = $definition;
        }
    }

    public function get(string $section, string $key): SettingDefinition
    {
        $id = $section . '.' . $key;
        if (!isset($this->definitions[$id])) {
            throw new \InvalidArgumentException(sprintf('Unknown setting "%s".', $id));
        }

        return $this->definitions[$id];
    }

    public function has(string $section, string $key): bool
    {
        return isset($this->definitions[$section . '.' . $key]);
    }

    /**
     * @return list<SettingDefinition>
     */
    public function bySection(string $section): array
    {
        return $this->bySection[$section] ?? [];
    }

    /**
     * @return list<string>
     */
    public function sections(): array
    {
        return array_keys($this->bySection);
    }

    /**
     * @return list<SettingDefinition>
     */
    public function all(): array
    {
        return array_values($this->definitions);
    }

    /**
     * @return iterable<SettingDefinition>
     */
    private function buildDefinitions(): iterable
    {
        // Company - used for invoice/offer headers, PDFs, app identity.
        yield new SettingDefinition(
            section: self::SECTION_COMPANY,
            key: self::KEY_COMPANY_NAME,
            type: SettingType::String,
            labelKey: 'settings.company.field.name',
            required: true,
            sortOrder: 10,
        );
        yield new SettingDefinition(
            section: self::SECTION_COMPANY,
            key: self::KEY_COMPANY_ID,
            type: SettingType::String,
            labelKey: 'settings.company.field.company_id',
            sortOrder: 20,
        );
        yield new SettingDefinition(
            section: self::SECTION_COMPANY,
            key: self::KEY_VAT_NUMBER,
            type: SettingType::String,
            labelKey: 'settings.company.field.vat_number',
            sortOrder: 30,
        );
        yield new SettingDefinition(
            section: self::SECTION_COMPANY,
            key: self::KEY_ADDRESS,
            type: SettingType::String,
            labelKey: 'settings.company.field.address',
            sortOrder: 40,
        );
        yield new SettingDefinition(
            section: self::SECTION_COMPANY,
            key: self::KEY_CITY,
            type: SettingType::String,
            labelKey: 'settings.company.field.city',
            sortOrder: 50,
        );
        yield new SettingDefinition(
            section: self::SECTION_COMPANY,
            key: self::KEY_COUNTRY,
            type: SettingType::String,
            labelKey: 'settings.company.field.country',
            sortOrder: 60,
        );
        yield new SettingDefinition(
            section: self::SECTION_COMPANY,
            key: self::KEY_PHONE,
            type: SettingType::String,
            labelKey: 'settings.company.field.phone',
            sortOrder: 70,
        );
        yield new SettingDefinition(
            section: self::SECTION_COMPANY,
            key: self::KEY_EMAIL,
            type: SettingType::Email,
            labelKey: 'settings.company.field.email',
            sortOrder: 80,
            // EmailType alone performs no server-side validation.
            constraints: [new Assert\Email(message: 'settings.company.error.email_invalid')],
        );
        yield new SettingDefinition(
            section: self::SECTION_COMPANY,
            key: self::KEY_WEBSITE,
            type: SettingType::Url,
            labelKey: 'settings.company.field.website',
            sortOrder: 90,
            // UrlType alone performs no server-side validation.
            constraints: [new Assert\Url(message: 'settings.company.error.website_invalid')],
        );
        yield new SettingDefinition(
            section: self::SECTION_COMPANY,
            key: self::KEY_LOGO_PATH,
            type: SettingType::FilePath,
            labelKey: 'settings.company.field.logo',
            sortOrder: 100,
        );

        // Regional - locale, timezone, formats.
        yield new SettingDefinition(
            section: self::SECTION_REGIONAL,
            key: self::KEY_DEFAULT_LOCALE,
            type: SettingType::Choice,
            default: RegionalDefaults::LOCALE,
            labelKey: 'settings.regional.field.locale',
            helpKey: 'settings.regional.help.locale',
            required: true,
            choices: SupportedLocale::choices(),
            sortOrder: 10,
        );
        yield new SettingDefinition(
            section: self::SECTION_REGIONAL,
            key: self::KEY_TIMEZONE,
            type: SettingType::Choice,
            default: RegionalDefaults::TIMEZONE,
            labelKey: 'settings.regional.field.timezone',
            helpKey: 'settings.regional.help.timezone',
            required: true,
            choices: $this->timezoneChoiceProvider->groupedChoices(),
            sortOrder: 20,
            constraints: [new Assert\Timezone(message: 'settings.regional.error.timezone_invalid')],
            autocomplete: true,
        );
        yield new SettingDefinition(
            section: self::SECTION_REGIONAL,
            key: self::KEY_DATE_FORMAT,
            type: SettingType::String,
            default: RegionalDefaults::DATE_FORMAT,
            labelKey: 'settings.regional.field.date_format',
            helpKey: 'settings.regional.help.date_format',
            required: true,
            sortOrder: 30,
        );
        yield new SettingDefinition(
            section: self::SECTION_REGIONAL,
            key: self::KEY_TIME_FORMAT,
            type: SettingType::String,
            default: RegionalDefaults::TIME_FORMAT,
            labelKey: 'settings.regional.field.time_format',
            helpKey: 'settings.regional.help.time_format',
            required: true,
            sortOrder: 40,
        );
        yield new SettingDefinition(
            section: self::SECTION_REGIONAL,
            key: self::KEY_MILEAGE_UNIT,
            type: SettingType::Choice,
            default: RegionalDefaults::MILEAGE_UNIT,
            labelKey: 'settings.regional.field.mileage_unit',
            helpKey: 'settings.regional.help.mileage_unit',
            required: true,
            choices: MileageUnit::choices(),
            sortOrder: 50,
            constraints: [new Assert\Choice(callback: [MileageUnit::class, 'values'], message: 'settings.regional.error.mileage_unit_invalid')],
        );

        // Currency & tax.
        yield new SettingDefinition(
            section: self::SECTION_CURRENCY,
            key: self::KEY_DEFAULT_CURRENCY_CODE,
            type: SettingType::Choice,
            default: Currency::DEFAULT->value,
            labelKey: 'settings.currency.field.code',
            helpKey: 'settings.currency.help',
            required: true,
            choices: Currency::choices(),
            sortOrder: 10,
        );
        yield new SettingDefinition(
            section: self::SECTION_CURRENCY,
            key: self::KEY_DEFAULT_TAX_RATE,
            type: SettingType::Float,
            labelKey: 'settings.tax.field.default_rate',
            helpKey: 'settings.tax.help',
            sortOrder: 20,
            constraints: [
                new Assert\PositiveOrZero(message: 'settings.tax.error.must_be_positive'),
                new Assert\LessThanOrEqual(value: 100, message: 'settings.tax.error.max'),
            ],
        );

        // Documents - numbering and defaults.
        yield new SettingDefinition(
            section: self::SECTION_DOCUMENTS,
            key: self::KEY_OFFER_NUMBER_PREFIX,
            type: SettingType::String,
            default: 'OF',
            labelKey: 'settings.documents.field.offer_prefix',
            helpKey: 'settings.documents.help.offer_prefix',
            required: true,
            sortOrder: 10,
            constraints: [new Assert\Regex(pattern: '/^[A-Z0-9_-]+$/i', message: 'settings.documents.error.prefix_invalid')],
        );
        yield new SettingDefinition(
            section: self::SECTION_DOCUMENTS,
            key: self::KEY_WORK_ORDER_NUMBER_PREFIX,
            type: SettingType::String,
            default: 'WO',
            labelKey: 'settings.documents.field.work_order_prefix',
            helpKey: 'settings.documents.help.work_order_prefix',
            required: true,
            sortOrder: 20,
            constraints: [new Assert\Regex(pattern: '/^[A-Z0-9_-]+$/i', message: 'settings.documents.error.prefix_invalid')],
        );
        yield new SettingDefinition(
            section: self::SECTION_DOCUMENTS,
            key: self::KEY_INVOICE_NUMBER_PREFIX,
            type: SettingType::String,
            default: 'INV',
            labelKey: 'settings.documents.field.invoice_prefix',
            helpKey: 'settings.documents.help.invoice_prefix',
            required: true,
            sortOrder: 30,
            constraints: [new Assert\Regex(pattern: '/^[A-Z0-9_-]+$/i', message: 'settings.documents.error.prefix_invalid')],
        );
        yield new SettingDefinition(
            section: self::SECTION_DOCUMENTS,
            key: self::KEY_DEFAULT_OFFER_VALIDITY_DAYS,
            type: SettingType::Integer,
            default: 14,
            labelKey: 'settings.documents.field.offer_validity',
            helpKey: 'settings.documents.help.offer_validity',
            sortOrder: 40,
            constraints: [
                new Assert\Positive(message: 'settings.documents.error.validity_positive'),
                new Assert\LessThanOrEqual(value: 3650, message: 'settings.documents.error.validity_too_large'),
            ],
        );
        yield new SettingDefinition(
            section: self::SECTION_DOCUMENTS,
            key: self::KEY_DOCUMENT_FOOTER_TEXT,
            type: SettingType::Text,
            labelKey: 'settings.documents.field.footer',
            helpKey: 'settings.documents.help.footer',
            sortOrder: 50,
            constraints: [new Assert\Length(max: 2000, maxMessage: 'settings.documents.error.footer_too_long')],
        );

        // Email / SMTP. Used by ClientDocumentMailer to send offer / work order PDFs.
        yield new SettingDefinition(
            section: self::SECTION_EMAIL,
            key: self::KEY_SMTP_HOST,
            type: SettingType::String,
            labelKey: 'settings.email.field.host',
            placeholder: 'smtp.example.com',
            sortOrder: 10,
        );
        yield new SettingDefinition(
            section: self::SECTION_EMAIL,
            key: self::KEY_SMTP_PORT,
            type: SettingType::Integer,
            labelKey: 'settings.email.field.port',
            placeholder: '587',
            sortOrder: 20,
            constraints: [new Assert\Range(notInRangeMessage: 'settings.email.error.port_invalid', min: 1, max: 65535)],
        );
        yield new SettingDefinition(
            section: self::SECTION_EMAIL,
            key: self::KEY_SMTP_ENCRYPTION,
            type: SettingType::Choice,
            default: SmtpEncryption::None->value,
            labelKey: 'settings.email.field.encryption',
            helpKey: 'settings.email.help.encryption',
            required: true,
            choices: SmtpEncryption::choices(),
            sortOrder: 30,
        );
        yield new SettingDefinition(
            section: self::SECTION_EMAIL,
            key: self::KEY_SMTP_USERNAME,
            type: SettingType::String,
            labelKey: 'settings.email.field.username',
            sortOrder: 40,
        );
        yield new SettingDefinition(
            section: self::SECTION_EMAIL,
            key: self::KEY_SMTP_PASSWORD,
            type: SettingType::Secret,
            labelKey: 'settings.email.field.password',
            helpKey: 'settings.email.help.password',
            sortOrder: 50,
        );
        yield new SettingDefinition(
            section: self::SECTION_EMAIL,
            key: self::KEY_SMTP_FROM_EMAIL,
            type: SettingType::Email,
            labelKey: 'settings.email.field.from_email',
            sortOrder: 60,
            constraints: [new Assert\Email(message: 'settings.email.error.from_email_invalid')],
        );
        yield new SettingDefinition(
            section: self::SECTION_EMAIL,
            key: self::KEY_SMTP_FROM_NAME,
            type: SettingType::String,
            labelKey: 'settings.email.field.from_name',
            sortOrder: 70,
        );
    }
}
