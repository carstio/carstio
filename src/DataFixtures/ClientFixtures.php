<?php

namespace App\DataFixtures;

use App\Entity\Client\Client;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

class ClientFixtures extends Fixture
{
    public const CLIENT_JOHN = 'client-john';
    public const CLIENT_ALICE = 'client-alice';
    public const CLIENT_BOB = 'client-bob';

    public function load(ObjectManager $manager): void
    {
        $john = new Client();
        $john->setName('John Doe');
        $john->setPhone('+1 555-0101');
        $john->setEmail('john.doe@example.com');
        $manager->persist($john);

        $alice = new Client();
        $alice->setName('Alice Smith');
        $alice->setPhone('+1 555-0102');
        $alice->setEmail('alice.smith@example.com');
        $manager->persist($alice);

        $bob = new Client();
        $bob->setName('Bob Brown');
        $bob->setPhone('+1 555-0103');
        $manager->persist($bob);

        $manager->flush();

        $this->addReference(self::CLIENT_JOHN, $john);
        $this->addReference(self::CLIENT_ALICE, $alice);
        $this->addReference(self::CLIENT_BOB, $bob);
    }
}
