<?php

namespace App\Service;

use App\Entity\Sales\Offer;
use App\Entity\Settings\RegionalDefaults;
use App\Entity\User;
use App\Enum\Currency;
use App\Service\Settings\SettingsProvider;
use App\Settings\SettingsRegistry;

class OfferFactory
{
    public function __construct(
        private readonly OfferNumberGenerator $numberGenerator,
        private readonly SettingsProvider $settings,
    ) {
    }

    public function createForUser(User $createdBy): Offer
    {
        $offer = new Offer();
        $offer->setNumber($this->numberGenerator->generate());
        $offer->setCreatedBy($createdBy);
        $offer->setCurrencyCode($this->settings->getString(
            SettingsRegistry::SECTION_CURRENCY,
            SettingsRegistry::KEY_DEFAULT_CURRENCY_CODE,
        ) ?? Currency::DEFAULT->value);

        $validityDays = $this->settings->getInt(
            SettingsRegistry::SECTION_DOCUMENTS,
            SettingsRegistry::KEY_DEFAULT_OFFER_VALIDITY_DAYS,
        );
        if ($validityDays !== null && $validityDays > 0) {
            $offer->setValidUntil($this->today()->modify('+' . $validityDays . ' days'));
        }

        return $offer;
    }

    private function today(): \DateTimeImmutable
    {
        $tz = $this->settings->getString(
            SettingsRegistry::SECTION_REGIONAL,
            SettingsRegistry::KEY_TIMEZONE,
        );
        $tz = $tz !== null && $tz !== '' ? $tz : RegionalDefaults::TIMEZONE;

        try {
            $timezone = new \DateTimeZone($tz);
        } catch (\Exception) {
            $timezone = new \DateTimeZone(RegionalDefaults::TIMEZONE);
        }

        return new \DateTimeImmutable('now', $timezone);
    }
}
