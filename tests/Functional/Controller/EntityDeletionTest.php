<?php

namespace App\Tests\Functional\Controller;

use App\Entity\Part\Part;
use App\Repository\Client\ClientRepository;
use App\Repository\Part\PartRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class EntityDeletionTest extends WebTestCase
{
    private function loginClient(): KernelBrowser
    {
        $client = static::createClient();
        $user = static::getContainer()->get(UserRepository::class)->findOneBy(['email' => 'admin@carstio.local']);
        $client->loginUser($user);

        return $client;
    }

    public function testDeletingPartWithoutDependenciesSucceeds(): void
    {
        $client = $this->loginClient();

        $em = static::getContainer()->get(EntityManagerInterface::class);
        $part = (new Part())
            ->setName('Disposable Widget')
            ->setSku('TEST-DEL-' . uniqid())
            ->setCostPrice('1.00')
            ->setSellPrice('2.00');
        $em->persist($part);
        $em->flush();
        $partId = $part->getId();

        $crawler = $client->request('GET', '/parts/' . $partId);
        $this->assertResponseIsSuccessful();

        $form = $crawler->filter('form[action$="/delete"]')->form();
        $client->submit($form);

        $this->assertResponseRedirects('/parts');
        $client->followRedirect();
        $this->assertSelectorTextContains('.alert-success', 'Part deleted.');

        static::getContainer()->get(EntityManagerInterface::class)->clear();
        $this->assertNull(static::getContainer()->get(PartRepository::class)->find($partId));
    }

    public function testDeletingPartUsedByWorkOrderItemIsBlocked(): void
    {
        $client = $this->loginClient();

        $parts = static::getContainer()->get(PartRepository::class);
        $oilFilter = $parts->findOneBy(['sku' => 'OF-001']);
        $this->assertNotNull($oilFilter, 'Fixture oil filter part must exist.');
        $partId = $oilFilter->getId();

        $crawler = $client->request('GET', '/parts/' . $partId);
        $form = $crawler->filter('form[action$="/delete"]')->form();
        $client->submit($form);

        $this->assertResponseRedirects('/parts/' . $partId);
        $client->followRedirect();

        $this->assertSelectorTextContains('.alert-danger', 'cannot be deleted');
        $this->assertSelectorTextNotContains('.alert-danger', 'SQLSTATE');
        $this->assertSelectorTextNotContains('.alert-danger', 'ForeignKey');

        static::getContainer()->get(EntityManagerInterface::class)->clear();
        $this->assertNotNull(
            static::getContainer()->get(PartRepository::class)->find($partId),
            'Part must remain in the database after a blocked deletion.',
        );
    }

    public function testDeletingClientWithDependentCarOfferIsBlocked(): void
    {
        $client = $this->loginClient();

        $clients = static::getContainer()->get(ClientRepository::class);
        $bob = $clients->findOneBy(['name' => 'Bob Brown']);
        $this->assertNotNull($bob, 'Fixture client Bob Brown must exist.');
        $clientId = $bob->getId();

        $crawler = $client->request('GET', '/clients/' . $clientId);
        $form = $crawler->filter('form[action$="/delete"]')->form();
        $client->submit($form);

        $this->assertResponseRedirects('/clients/' . $clientId);
        $client->followRedirect();

        $this->assertSelectorTextContains('.alert-danger', 'cannot be deleted');
        $this->assertSelectorTextNotContains('.alert-danger', 'SQLSTATE');

        static::getContainer()->get(EntityManagerInterface::class)->clear();
        $this->assertNotNull(
            static::getContainer()->get(ClientRepository::class)->find($clientId),
            'Client must remain in the database after a blocked deletion.',
        );
    }
}
