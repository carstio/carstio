<?php

namespace App\DataFixtures;

use App\Entity\Client\Client;
use App\Entity\Client\Company;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;

class CompanyFixtures extends Fixture implements DependentFixtureInterface
{
    public const COMPANY_JOHN = 'company-john';

    public function load(ObjectManager $manager): void
    {
        $company = new Company();
        $company->setClient($this->getReference(ClientFixtures::CLIENT_JOHN, Client::class));
        $company->setName('Doe Auto Services Ltd');
        $company->setCity('Springfield');
        $company->setAddress('123 Main St');
        $company->setIdentifier('123456789');
        $company->setVatIdentifier('BG123456789');
        $company->setVat(true);
        $manager->persist($company);

        $manager->flush();

        $this->addReference(self::COMPANY_JOHN, $company);
    }

    public function getDependencies(): array
    {
        return [
            ClientFixtures::class,
        ];
    }
}
