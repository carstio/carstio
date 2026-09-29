<?php

namespace App\Tests\Functional\Controller;

use App\Entity\Car\Car;
use App\Entity\Client\Client;
use App\Entity\Sales\Offer;
use App\Enum\OfferStatus;
use App\Repository\Sales\OfferRepository;
use App\Repository\UserRepository;
use App\Repository\Workshop\WorkOrderRepository;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class OfferControllerTest extends WebTestCase
{
    private function loginClient(string $email = 'admin@carstio.local'): \Symfony\Bundle\FrameworkBundle\KernelBrowser
    {
        $client = static::createClient();
        $user = static::getContainer()->get(UserRepository::class)->findOneBy(['email' => $email]);
        $client->loginUser($user);

        return $client;
    }

    public function testIndexLoads(): void
    {
        $client = $this->loginClient();
        $client->request('GET', '/offers');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h1', 'Offers');
    }

    public function testIndexShowsFixtureOffers(): void
    {
        $client = $this->loginClient();
        $client->request('GET', '/offers');

        $this->assertSelectorExists('table');
        $this->assertSelectorTextContains('table', 'OF-2026-0001');
    }

    public function testAnonymousCannotAccessOffers(): void
    {
        $client = static::createClient();
        $client->request('GET', '/offers');

        $this->assertResponseRedirects('/login');
    }

    public function testNewOfferFormDoesNotExposeOwnerField(): void
    {
        $client = $this->loginClient();
        $crawler = $client->request('GET', '/offers/new');

        $this->assertResponseIsSuccessful();
        $this->assertCount(0, $crawler->filter('select[name="offer[createdBy]"], input[name="offer[createdBy]"]'));
    }

    public function testCreatedOfferIsOwnedByAuthenticatedUser(): void
    {
        $client = $this->loginClient('mechanic@carstio.local');
        $crawler = $client->request('GET', '/offers/new');
        $this->assertResponseIsSuccessful();

        $offerRepository = static::getContainer()->get(OfferRepository::class);
        $existingIds = array_map(fn (Offer $o) => $o->getId(), $offerRepository->findAll());

        $clientRepo = static::getContainer()->get('doctrine')->getRepository(Client::class);
        $carRepo = static::getContainer()->get('doctrine')->getRepository(Car::class);
        $clientEntity = $clientRepo->findOneBy(['name' => 'John Doe']);
        $carEntity = $carRepo->findOneBy(['client' => $clientEntity]);
        $this->assertNotNull($clientEntity);
        $this->assertNotNull($carEntity);

        $form = $crawler->filter('form[name="offer"]')->form();
        $form['offer[client]'] = (string) $clientEntity->getId();
        $form['offer[car]'] = (string) $carEntity->getId();

        $client->submit($form);

        $this->assertResponseRedirects();

        $created = null;
        foreach ($offerRepository->findAll() as $offer) {
            if (!in_array($offer->getId(), $existingIds, true)) {
                $created = $offer;
                break;
            }
        }

        $this->assertNotNull($created, 'A new offer should have been created.');
        $this->assertNotNull($created->getCreatedBy());
        $this->assertSame('mechanic@carstio.local', $created->getCreatedBy()->getEmail());
    }

    public function testConvertApprovedOfferCreatesWorkOrder(): void
    {
        $client = $this->loginClient();

        $offerRepository = static::getContainer()->get(OfferRepository::class);
        $workOrderRepository = static::getContainer()->get(WorkOrderRepository::class);

        $offer = $offerRepository->findOneBy(['number' => 'OF-2026-0003']);
        $this->assertNotNull($offer);
        $this->assertSame(OfferStatus::Approved, $offer->getStatus());

        $workOrdersBefore = count($workOrderRepository->findAll());

        $crawler = $client->request('GET', '/offers/' . $offer->getId());
        $this->assertResponseIsSuccessful();

        $form = $crawler->filter('form[action="/offers/' . $offer->getId() . '/convert"]')->form();
        $client->submit($form);

        $this->assertResponseRedirects();

        $workOrdersAfter = $workOrderRepository->findAll();
        $this->assertCount($workOrdersBefore + 1, $workOrdersAfter);

        static::getContainer()->get('doctrine')->getManager()->clear();
        $offer = $offerRepository->findOneBy(['number' => 'OF-2026-0003']);
        $this->assertSame(OfferStatus::Converted, $offer->getStatus());
        $this->assertNotNull($offer->getConvertedAt());
        $this->assertNotNull($offer->getWorkOrder());
        $this->assertSame($offer->getClient()->getId(), $offer->getWorkOrder()->getClient()->getId());
        $this->assertSame($offer->getCar()->getId(), $offer->getWorkOrder()->getCar()->getId());
        $this->assertCount($offer->getItems()->count(), $offer->getWorkOrder()->getItems());
        $this->assertSame($offer->getTotalAmount(), $offer->getWorkOrder()->getTotalAmount());
        $this->assertSame($offer->getCurrencyCode(), $offer->getWorkOrder()->getCurrencyCode());
    }

    public function testDraftOfferShowPageDoesNotRenderConvertForm(): void
    {
        $client = $this->loginClient();

        $offerRepository = static::getContainer()->get(OfferRepository::class);
        $offer = $offerRepository->findOneBy(['number' => 'OF-2026-0001']); // draft
        $this->assertSame(OfferStatus::Draft, $offer->getStatus());

        $crawler = $client->request('GET', '/offers/' . $offer->getId());
        $this->assertResponseIsSuccessful();

        $this->assertCount(0, $crawler->filter('form[action="/offers/' . $offer->getId() . '/convert"]'));
    }

    public function testEditAcceptsItemsWithEmptyDiscountAndTaxRate(): void
    {
        $client = $this->loginClient();

        $offerRepository = static::getContainer()->get(OfferRepository::class);
        $offer = $offerRepository->findOneBy(['number' => 'OF-2026-0001']);
        $this->assertNotNull($offer);
        $this->assertGreaterThan(0, $offer->getItems()->count());

        $crawler = $client->request('GET', '/offers/' . $offer->getId() . '/edit');
        $this->assertResponseIsSuccessful();

        $form = $crawler->filter('form[name="offer"]')->form();
        $values = $form->getPhpValues();

        $this->assertArrayHasKey('items', $values['offer']);
        foreach ($values['offer']['items'] as $index => $item) {
            $values['offer']['items'][$index]['discountAmount'] = '';
            $values['offer']['items'][$index]['taxRate'] = '';
        }

        $client->request($form->getMethod(), $form->getUri(), $values);

        $this->assertResponseRedirects('/offers/' . $offer->getId());

        static::getContainer()->get('doctrine')->getManager()->clear();
        $offer = $offerRepository->findOneBy(['number' => 'OF-2026-0001']);
        $this->assertNotNull($offer);
        $this->assertSame('0.00', $offer->getDiscountAmount());
        $this->assertSame('0.00', $offer->getTaxAmount());

        foreach ($offer->getItems() as $item) {
            $this->assertSame('0.00', $item->getDiscountAmount());
            $this->assertSame('0.00', $item->getTaxRate());
        }
    }

    public function testConvertedStatusCannotBeChosenInEditForm(): void
    {
        $client = $this->loginClient();

        $offerRepository = static::getContainer()->get(OfferRepository::class);
        $offer = $offerRepository->findOneBy(['number' => 'OF-2026-0001']);
        $this->assertNotNull($offer);

        $crawler = $client->request('GET', '/offers/' . $offer->getId() . '/edit');
        $this->assertResponseIsSuccessful();

        $options = $crawler->filter('select[name="offer[status]"] option')->each(fn ($node) => $node->attr('value'));
        $this->assertContains('draft', $options);
        $this->assertNotContains('converted', $options, '"Converted" is reserved for the conversion workflow.');

        // Bypass the dropdown and post the value directly - the server must reject it.
        $form = $crawler->filter('form[name="offer"]')->form();
        $values = $form->getPhpValues();
        $values['offer']['status'] = 'converted';
        $client->request($form->getMethod(), $form->getUri(), $values);

        $this->assertResponseStatusCodeSame(422);

        static::getContainer()->get('doctrine')->getManager()->clear();
        $this->assertSame(OfferStatus::Draft, $offerRepository->findOneBy(['number' => 'OF-2026-0001'])->getStatus());
    }
}
