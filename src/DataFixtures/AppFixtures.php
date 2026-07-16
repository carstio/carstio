<?php

namespace App\DataFixtures;

use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;

/**
 * Main entry point that loads all fixture groups in the correct order.
 */
class AppFixtures extends Fixture implements DependentFixtureInterface
{
    public function load(ObjectManager $manager): void
    {
        // All data is loaded via dependencies.
    }

    public function getDependencies(): array
    {
        return [
            UserFixtures::class,
            SettingsFixtures::class,
            BrandModelFixtures::class,
            ClientFixtures::class,
            CarFixtures::class,
            CompanyFixtures::class,
            PartFixtures::class,
            WorkOrderFixtures::class,
            PaymentFixtures::class,
            OfferFixtures::class,
        ];
    }
}
