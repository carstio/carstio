<?php

namespace App\Tests\Unit\Service;

use App\Entity\User;
use App\Service\Settings\SettingsProvider;
use App\Service\WorkOrderFactory;
use App\Service\WorkOrderNumberGenerator;
use App\Settings\SettingsRegistry;
use PHPUnit\Framework\TestCase;

class WorkOrderFactoryTest extends TestCase
{
    public function testCreateForUserAssignsCreatorAndNumber(): void
    {
        $factory = new WorkOrderFactory(
            $this->mockNumberGenerator('WO-2026-0099'),
            $this->mockSettingsProvider('EUR'),
        );

        $user = $this->mockUser('mechanic@carstio.local', 'ROLE_MECHANIC');
        $workOrder = $factory->createForUser($user);

        $this->assertSame('WO-2026-0099', $workOrder->getNumber());
        $this->assertSame($user, $workOrder->getCreatedBy());
        $this->assertSame('EUR', $workOrder->getCurrencyCode());
    }

    public function testCreateForUserSnapshotsCurrentSettingsCurrency(): void
    {
        $factory = new WorkOrderFactory(
            $this->mockNumberGenerator('WO-2026-0100'),
            $this->mockSettingsProvider('USD'),
        );

        $workOrder = $factory->createForUser($this->mockUser('admin@carstio.local', 'ROLE_ADMIN'));

        $this->assertSame('USD', $workOrder->getCurrencyCode());
    }

    private function mockNumberGenerator(string $number): WorkOrderNumberGenerator
    {
        $generator = $this->createStub(WorkOrderNumberGenerator::class);
        $generator->method('generate')->willReturn($number);

        return $generator;
    }

    private function mockSettingsProvider(string $currency): SettingsProvider
    {
        $provider = $this->createStub(SettingsProvider::class);
        $provider->method('getString')->willReturnMap([
            [SettingsRegistry::SECTION_CURRENCY, SettingsRegistry::KEY_DEFAULT_CURRENCY_CODE, $currency],
        ]);

        return $provider;
    }

    private function mockUser(string $email, string $role): User
    {
        $user = new User();
        $user->setEmail($email);
        $user->setFullName('Test');
        $user->setRoles([$role]);

        return $user;
    }
}
