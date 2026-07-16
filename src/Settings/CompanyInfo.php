<?php

namespace App\Settings;

/**
 * Read-only snapshot of the company identity sourced from settings. Used
 * by PDF templates and any other view that needs the workshop's name,
 * address, and contact details.
 *
 * Property names match what the PDF base template expects, so the
 * presentation layer doesn't need to know that the data lives in the
 * generic settings store.
 */
final readonly class CompanyInfo
{
    public function __construct(
        public ?string $companyName,
        public ?string $companyId,
        public ?string $vatNumber,
        public ?string $address,
        public ?string $city,
        public ?string $country,
        public ?string $phone,
        public ?string $email,
        public ?string $website,
        public ?string $logoPath,
    ) {
    }

    public function hasLogo(): bool
    {
        return $this->logoPath !== null && $this->logoPath !== '';
    }
}
