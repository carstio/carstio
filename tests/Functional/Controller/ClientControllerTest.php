<?php

namespace App\Tests\Functional\Controller;

use App\Repository\Client\ClientRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class ClientControllerTest extends WebTestCase
{
    private function loginClient(): \Symfony\Bundle\FrameworkBundle\KernelBrowser
    {
        $client = static::createClient();
        $user = static::getContainer()->get(UserRepository::class)->findOneBy(['email' => 'admin@carstio.local']);
        $client->loginUser($user);

        return $client;
    }

    public function testIndexLoads(): void
    {
        $client = $this->loginClient();
        $client->request('GET', '/clients');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h1', 'Clients');
    }

    public function testIndexShowsClients(): void
    {
        $client = $this->loginClient();
        $client->request('GET', '/clients');

        $this->assertSelectorExists('table');
        $this->assertSelectorTextContains('table', 'John Doe');
    }

    public function testSearchFiltersByName(): void
    {
        $client = $this->loginClient();
        $client->request('GET', '/clients?q=Alice');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('table', 'Alice Smith');
        $this->assertSelectorTextNotContains('table', 'Bob Brown');
    }

    public function testNewClientPageLoads(): void
    {
        $client = $this->loginClient();
        $client->request('GET', '/clients/new');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorExists('form');
    }

    public function testCreateClient(): void
    {
        $client = $this->loginClient();
        $client->request('GET', '/clients/new');

        $client->submitForm('Save', [
            'client[name]' => 'Test Client',
            'client[phone]' => '+1 555-9999',
            'client[email]' => 'test@example.com',
        ]);

        $this->assertResponseRedirects();
        $client->followRedirect();
        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('.alert-success', 'Client created successfully');
    }

    public function testCreateClientValidation(): void
    {
        $client = $this->loginClient();
        $client->request('GET', '/clients/new');

        $client->submitForm('Save', [
            'client[name]' => '',
            'client[phone]' => '',
        ]);

        $this->assertResponseIsUnprocessable();
    }

    public function testCreateClientRejectsInvalidEmailAndOverlongName(): void
    {
        $client = $this->loginClient();
        $client->request('GET', '/clients/new');

        $client->submitForm('Save', [
            'client[name]' => str_repeat('x', 256),
            'client[phone]' => '+1 555-0000',
            'client[email]' => "not-an-email'+alert(1)+'",
        ]);

        // Must be a validation error, never a database error / 500.
        $this->assertResponseIsUnprocessable();
        $this->assertSelectorExists('.invalid-feedback');
    }

    public function testShowClient(): void
    {
        $client = $this->loginClient();
        $crawler = $client->request('GET', '/clients');

        $link = $crawler->filter('table a')->first()->link();
        $client->click($link);

        $this->assertResponseIsSuccessful();
    }

    public function testShowClientListsRelatedWorkOrdersAndOffers(): void
    {
        $client = $this->loginClient();
        $john = static::getContainer()->get(ClientRepository::class)->findOneBy(['name' => 'John Doe']);
        self::assertNotNull($john, 'Fixture client John Doe must exist.');

        $crawler = $client->request('GET', '/clients/' . $john->getId());

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('body', 'Related work orders');
        $this->assertSelectorTextContains('body', 'Related offers');

        // John owns WO-2026-0001 in the fixtures.
        $this->assertSelectorTextContains('body', 'WO-2026-0001');
        // John has at least OF-2026-0003 and OF-2026-0004.
        $this->assertSelectorTextContains('body', 'OF-2026-0003');
        $this->assertSelectorTextContains('body', 'OF-2026-0004');

        // Alice's documents must NOT leak onto John's page.
        $this->assertSelectorTextNotContains('body', 'WO-2026-0002');
        $this->assertSelectorTextNotContains('body', 'OF-2026-0002');

        // The "view" links should point at the real offer/work-order routes.
        $woLink = $crawler->filter('a[href$="/work-orders/' . $this->fixtureWorkOrderIdByNumber('WO-2026-0001') . '"]');
        $this->assertGreaterThan(0, $woLink->count(), 'Related work orders table must link to the work order show page.');

        $offerLink = $crawler->filter('a[href$="/offers/' . $this->fixtureOfferIdByNumber('OF-2026-0003') . '"]');
        $this->assertGreaterThan(0, $offerLink->count(), 'Related offers table must link to the offer show page.');
    }

    public function testShowClientShowsEmptyStatesForBrandNewClient(): void
    {
        $client = $this->loginClient();

        $em = static::getContainer()->get(EntityManagerInterface::class);
        $newClient = (new \App\Entity\Client\Client())
            ->setName('Fresh Test Client ' . uniqid())
            ->setPhone('+1 555-0000');
        $em->persist($newClient);
        $em->flush();

        $client->request('GET', '/clients/' . $newClient->getId());

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('body', 'No work orders for this client yet.');
        $this->assertSelectorTextContains('body', 'No offers for this client yet.');
    }

    private function fixtureWorkOrderIdByNumber(string $number): int
    {
        $repo = static::getContainer()->get(\App\Repository\Workshop\WorkOrderRepository::class);
        $wo = $repo->findOneBy(['number' => $number]);
        self::assertNotNull($wo, sprintf('Fixture work order %s must exist.', $number));

        return (int) $wo->getId();
    }

    private function fixtureOfferIdByNumber(string $number): int
    {
        $repo = static::getContainer()->get(\App\Repository\Sales\OfferRepository::class);
        $offer = $repo->findOneBy(['number' => $number]);
        self::assertNotNull($offer, sprintf('Fixture offer %s must exist.', $number));

        return (int) $offer->getId();
    }
}
