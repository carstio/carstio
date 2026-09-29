<?php

namespace App\Tests\Unit\Service;

use App\Entity\Settings\RegionalDefaults;
use App\Service\Formatting\DateFormatter;
use App\Service\Settings\SettingsProvider;
use App\Settings\SettingsRegistry;
use PHPUnit\Framework\TestCase;

class DateFormatterTest extends TestCase
{
    public function testFormatsDateWithConfiguredFormat(): void
    {
        $formatter = new DateFormatter($this->settings([
            SettingsRegistry::SECTION_REGIONAL . '.' . SettingsRegistry::KEY_DATE_FORMAT => 'd.m.Y',
            SettingsRegistry::SECTION_REGIONAL . '.' . SettingsRegistry::KEY_TIMEZONE => 'UTC',
        ]));

        $value = new \DateTimeImmutable('2026-04-25 10:30:00', new \DateTimeZone('UTC'));

        $this->assertSame('25.04.2026', $formatter->formatDate($value));
    }

    public function testFormatsDateTimeCombinesDateAndTimeFormats(): void
    {
        $formatter = new DateFormatter($this->settings([
            SettingsRegistry::SECTION_REGIONAL . '.' . SettingsRegistry::KEY_DATE_FORMAT => 'd.m.Y',
            SettingsRegistry::SECTION_REGIONAL . '.' . SettingsRegistry::KEY_TIME_FORMAT => 'H:i',
            SettingsRegistry::SECTION_REGIONAL . '.' . SettingsRegistry::KEY_TIMEZONE => 'UTC',
        ]));

        $value = new \DateTimeImmutable('2026-04-25 14:30:00', new \DateTimeZone('UTC'));

        $this->assertSame('25.04.2026 14:30', $formatter->formatDateTime($value));
    }

    public function testAppliesConfiguredTimezone(): void
    {
        $formatter = new DateFormatter($this->settings([
            SettingsRegistry::SECTION_REGIONAL . '.' . SettingsRegistry::KEY_DATE_FORMAT => 'Y-m-d',
            SettingsRegistry::SECTION_REGIONAL . '.' . SettingsRegistry::KEY_TIME_FORMAT => 'H:i',
            SettingsRegistry::SECTION_REGIONAL . '.' . SettingsRegistry::KEY_TIMEZONE => 'Europe/Sofia',
        ]));

        // 22:30 UTC is 01:30 the next day in Sofia (summer) or 00:30 in winter.
        // Pick a winter date to avoid DST ambiguity (Sofia winter = UTC+2).
        $value = new \DateTimeImmutable('2026-01-15 22:30:00', new \DateTimeZone('UTC'));

        $this->assertSame('2026-01-16 00:30', $formatter->formatDateTime($value));
    }

    public function testReturnsEmptyStringForNull(): void
    {
        $formatter = new DateFormatter($this->settings([]));

        $this->assertSame('', $formatter->formatDate(null));
        $this->assertSame('', $formatter->formatTime(null));
        $this->assertSame('', $formatter->formatDateTime(null));
    }

    public function testFallsBackToRegionalDefaultsWhenSettingsEmpty(): void
    {
        $formatter = new DateFormatter($this->settings([]));

        $value = new \DateTimeImmutable('2026-04-25 10:00:00', new \DateTimeZone(RegionalDefaults::TIMEZONE));

        $this->assertSame('2026-04-25', $formatter->formatDate($value));
        $this->assertSame('10:00', $formatter->formatTime($value));
    }

    public function testFallsBackToDefaultTimezoneOnInvalidConfig(): void
    {
        $formatter = new DateFormatter($this->settings([
            SettingsRegistry::SECTION_REGIONAL . '.' . SettingsRegistry::KEY_DATE_FORMAT => 'Y-m-d',
            SettingsRegistry::SECTION_REGIONAL . '.' . SettingsRegistry::KEY_TIMEZONE => 'Not/AReal/Zone',
        ]));

        $value = new \DateTimeImmutable('2026-04-25 10:00:00', new \DateTimeZone('UTC'));

        $this->assertSame('2026-04-25', $formatter->formatDate($value));
    }

    /**
     * @param array<string, ?string> $values keyed by "section.key"
     */
    private function settings(array $values): SettingsProvider
    {
        $provider = $this->createStub(SettingsProvider::class);
        $provider->method('getString')->willReturnCallback(
            static fn (string $section, string $key): ?string => $values[$section . '.' . $key] ?? null,
        );

        return $provider;
    }
}
