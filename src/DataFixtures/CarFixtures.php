<?php

namespace App\DataFixtures;

use App\Entity\Car\Car;
use App\Entity\Car\Model;
use App\Entity\Client\Client;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;

class CarFixtures extends Fixture implements DependentFixtureInterface
{
    public const CAR_JOHN_COROLLA = 'car-john-corolla';
    public const CAR_JOHN_3SERIES = 'car-john-3series';
    public const CAR_ALICE_X5 = 'car-alice-x5';
    public const CAR_BOB_FOCUS = 'car-bob-focus';

    public function load(ObjectManager $manager): void
    {
        $car1 = new Car();
        $car1->setClient($this->getReference(ClientFixtures::CLIENT_JOHN, Client::class));
        $car1->setModel($this->getReference(BrandModelFixtures::MODEL_COROLLA, Model::class));
        $car1->setVin('JTDKN3DU5A0123456');
        $car1->setLicensePlate('CA 1234 AB');
        $manager->persist($car1);

        $car2 = new Car();
        $car2->setClient($this->getReference(ClientFixtures::CLIENT_JOHN, Client::class));
        $car2->setModel($this->getReference(BrandModelFixtures::MODEL_3_SERIES, Model::class));
        $car2->setLicensePlate('CB 5678 CD');
        $manager->persist($car2);

        $car3 = new Car();
        $car3->setClient($this->getReference(ClientFixtures::CLIENT_ALICE, Client::class));
        $car3->setModel($this->getReference(BrandModelFixtures::MODEL_X5, Model::class));
        $car3->setVin('5UXCR6C55KLL12345');
        $car3->setLicensePlate('PB 9013 KE');
        $manager->persist($car3);

        $car4 = new Car();
        $car4->setClient($this->getReference(ClientFixtures::CLIENT_BOB, Client::class));
        $car4->setModel($this->getReference(BrandModelFixtures::MODEL_FOCUS, Model::class));
        $car4->setVin('1FAHP3F29CL123456');
        $manager->persist($car4);

        $manager->flush();

        $this->addReference(self::CAR_JOHN_COROLLA, $car1);
        $this->addReference(self::CAR_JOHN_3SERIES, $car2);
        $this->addReference(self::CAR_ALICE_X5, $car3);
        $this->addReference(self::CAR_BOB_FOCUS, $car4);
    }

    public function getDependencies(): array
    {
        return [
            ClientFixtures::class,
            BrandModelFixtures::class,
        ];
    }
}
