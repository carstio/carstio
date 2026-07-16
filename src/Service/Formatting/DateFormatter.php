<?php

declare(strict_types=1);

namespace App\Service\Formatting;

use App\Entity\Settings\RegionalDefaults;
use App\Service\Settings\SettingsProvider;
use App\Settings\SettingsRegistry;

/**
 * Formats dates and datetimes using the configured regional settings.
 *
 * The configured date format, time format, and timezone come from the
 * Settings store via {@see SettingsProvider}. The provider already caches
 * its rows per request so repeated calls in templates do not hit the DB.
 *
 * Returns an empty string when given null so Twig templates can drop the
 * filter inline without conditional guards (matches the behaviour of the
 * built-in `|date` filter on null values).
 */
final class DateFormatter
{
    public function __construct(
        private readonly SettingsProvider $settings,
    ) {
    }

    public function formatDate(?\DateTimeInterface $value): string
    {
        if ($value === null) {
            return '';
        }

        return $this->inDisplayTimezone($value)->format($this->dateFormat());
    }

    public function formatTime(?\DateTimeInterface $value): string
    {
        if ($value === null) {
            return '';
        }

        return $this->inDisplayTimezone($value)->format($this->timeFormat());
    }

    public function formatDateTime(?\DateTimeInterface $value): string
    {
        if ($value === null) {
            return '';
        }

        return $this->inDisplayTimezone($value)->format($this->dateFormat() . ' ' . $this->timeFormat());
    }

    private function dateFormat(): string
    {
        $value = $this->settings->getString(
            SettingsRegistry::SECTION_REGIONAL,
            SettingsRegistry::KEY_DATE_FORMAT,
        );

        return $value !== null && $value !== '' ? $value : RegionalDefaults::DATE_FORMAT;
    }

    private function timeFormat(): string
    {
        $value = $this->settings->getString(
            SettingsRegistry::SECTION_REGIONAL,
            SettingsRegistry::KEY_TIME_FORMAT,
        );

        return $value !== null && $value !== '' ? $value : RegionalDefaults::TIME_FORMAT;
    }

    private function inDisplayTimezone(\DateTimeInterface $value): \DateTimeImmutable
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

        $immutable = $value instanceof \DateTimeImmutable
            ? $value
            : \DateTimeImmutable::createFromInterface($value);

        return $immutable->setTimezone($timezone);
    }
}
