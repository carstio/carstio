<?php

namespace App\Service\Pdf;

use App\Service\Settings\SettingsProvider;
use App\Settings\CompanyInfo;
use App\Settings\SettingsRegistry;

/**
 * Read-only facade used by PDF templates to access company identity and
 * resolve the absolute path of the uploaded logo.
 *
 * Kept separate from the generic SettingsProvider so PDF rendering has a
 * narrow, presentation-oriented API and so logo path resolution (which
 * needs the project dir) is testable in isolation.
 */
class CompanyProfileProvider
{
    public function __construct(
        private readonly SettingsProvider $settings,
        private readonly string $projectDir,
    ) {
    }

    public function getCompany(): CompanyInfo
    {
        return new CompanyInfo(
            companyName: $this->settings->getString(SettingsRegistry::SECTION_COMPANY, SettingsRegistry::KEY_COMPANY_NAME),
            companyId: $this->settings->getString(SettingsRegistry::SECTION_COMPANY, SettingsRegistry::KEY_COMPANY_ID),
            vatNumber: $this->settings->getString(SettingsRegistry::SECTION_COMPANY, SettingsRegistry::KEY_VAT_NUMBER),
            address: $this->settings->getString(SettingsRegistry::SECTION_COMPANY, SettingsRegistry::KEY_ADDRESS),
            city: $this->settings->getString(SettingsRegistry::SECTION_COMPANY, SettingsRegistry::KEY_CITY),
            country: $this->settings->getString(SettingsRegistry::SECTION_COMPANY, SettingsRegistry::KEY_COUNTRY),
            phone: $this->settings->getString(SettingsRegistry::SECTION_COMPANY, SettingsRegistry::KEY_PHONE),
            email: $this->settings->getString(SettingsRegistry::SECTION_COMPANY, SettingsRegistry::KEY_EMAIL),
            website: $this->settings->getString(SettingsRegistry::SECTION_COMPANY, SettingsRegistry::KEY_WEBSITE),
            logoPath: $this->settings->getString(SettingsRegistry::SECTION_COMPANY, SettingsRegistry::KEY_LOGO_PATH),
        );
    }

    /**
     * Configurable footer text shown on every PDF page above the
     * generated-by notice. Returns null when the setting is empty so
     * templates can omit the block entirely.
     */
    public function getDocumentFooterText(): ?string
    {
        $value = $this->settings->getString(
            SettingsRegistry::SECTION_DOCUMENTS,
            SettingsRegistry::KEY_DOCUMENT_FOOTER_TEXT,
        );

        return $value !== null && trim($value) !== '' ? $value : null;
    }

    /**
     * Returns the absolute filesystem path to the company logo, or null
     * if no logo is configured or the file cannot be read. Dompdf needs
     * real paths (or data URIs) - it does not fetch remote URLs.
     */
    public function getLogoAbsolutePath(): ?string
    {
        $relative = $this->getCompany()->logoPath;
        if ($relative === null || $relative === '') {
            return null;
        }

        $absolute = rtrim($this->projectDir, '/') . '/public/' . ltrim($relative, '/');
        if (!is_file($absolute) || !is_readable($absolute)) {
            return null;
        }

        return $absolute;
    }
}
