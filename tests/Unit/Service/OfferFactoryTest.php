<?php

namespace App\Tests\Unit\Service;

use App\Entity\User;
use App\Service\OfferFactory;
use App\Service\OfferNumberGenerator;
use App\Service\Settings\SettingsProvider;
use App\Settings\SettingsRegistry;
use PHPUnit\Framework\TestCase;

class OfferFactoryTest extends TestCase
{
    public function testCreateForUserAssignsCreatorAndNumber(): void
    {
        $factory = new OfferFactory(
            $this->mockNumberGenerator('OF-2026-0099'),
            $this->mockSettingsProvider('EUR', validityDays: null),
        );

        $user = $this->mockUser();
        $offer = $factory->createForUser($user);

        $this->assertSame('OF-2026-0099', $offer->getNumber());
        $this->assertSame($user, $offer->getCreatedBy());
        $this->assertSame('EUR', $offer->getCurrencyCode());
        $this->assertNull($offer->getValidUntil());
    }

    public function testCreateForUserSnapshotsCurrentSettingsCurrency(): void
    {
        $factory = new OfferFactory(
            $this->mockNumberGenerator('OF-2026-0100'),
            $this->mockSettingsProvider('USD', validityDays: null),
        );

        $offer = $factory->createForUser($this->mockUser());

        $this->assertSame('USD', $offer->getCurrencyCode());
    }

    public function testCreateForUserAppliesConfiguredValidityDays(): void
    {
        $factory = new OfferFactory(
            $this->mockNumberGenerator('OF-2026-0101'),
            $this->mockSettingsProvider('EUR', validityDays: 30, timezone: 'Europe/Sofia'),
        );

        $before = new \DateTimeImmutable('now', new \DateTimeZone('Europe/Sofia'));
        $offer = $factory->createForUser($this->mockUser());
        $after = new \DateTimeImmutable('now', new \DateTimeZone('Europe/Sofia'));

        $validUntil = $offer->getValidUntil();
        $this->assertNotNull($validUntil);

        $minExpected = $before->modify('+30 days')->setTime(0, 0)->getTimestamp();
        $maxExpected = $after->modify('+30 days')->setTime(23, 59, 59)->getTimestamp();
        $this->assertGreaterThanOrEqual($minExpected, $validUntil->getTimestamp());
        $this->assertLessThanOrEqual($maxExpected, $validUntil->getTimestamp());
    }

    private function mockNumberGenerator(string $number): OfferNumberGenerator
    {
        $generator = $this->createStub(OfferNumberGenerator::class);
        $generator->method('generate')->willReturn($number);

        return $generator;
    }

    private function mockSettingsProvider(string $currency, ?int $validityDays, ?string $timezone = null): SettingsProvider
    {
        $provider = $this->createStub(SettingsProvider::class);
        $provider->method('getString')->willReturnCallback(
            static function (string $section, string $key) use ($currency, $timezone): ?string {
                return match ([$section, $key]) {
                    [SettingsRegistry::SECTION_CURRENCY, SettingsRegistry::KEY_DEFAULT_CURRENCY_CODE] => $currency,
                    [SettingsRegistry::SECTION_REGIONAL, SettingsRegistry::KEY_TIMEZONE] => $timezone,
                    default => null,
                };
            },
        );
        $provider->method('getInt')->willReturnCallback(
            static function (string $section, string $key) use ($validityDays): ?int {
                return [$section, $key] === [SettingsRegistry::SECTION_DOCUMENTS, SettingsRegistry::KEY_DEFAULT_OFFER_VALIDITY_DAYS]
                    ? $validityDays
                    : null;
            },
        );

        return $provider;
    }

    private function mockUser(): User
    {
        $user = new User();
        $user->setEmail('mechanic@carstio.local');
        $user->setFullName('Mechanic');
        $user->setRoles(['ROLE_MECHANIC']);

        return $user;
    }
}
