<?php

namespace App\Tests\Functional\Controller;

use App\Entity\Car\Car;
use App\Entity\Client\Client;
use App\Entity\Sales\Offer;
use App\Entity\Sales\OfferItem;
use App\Enum\OfferItemType as OfferItemKind;
use App\Repository\Sales\OfferRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Mirrors WorkOrderItemDeletionTest for Offers. Same form pattern, same
 * shared collection JS controller, so the same defenses must hold.
 */
class OfferItemDeletionTest extends WebTestCase
{
    private function loginClient(string $email = 'admin@carstio.local'): KernelBrowser
    {
        $client = static::createClient();
        $user = static::getContainer()->get(UserRepository::class)->findOneBy(['email' => $email]);
        $client->loginUser($user);

        return $client;
    }

    public function testEditCanRemoveLaborItemAndSubmit(): void
    {
        $client = $this->loginClient();
        $offer = $this->createTestOffer('OF-IT-DEL-LABOR');
        $countBefore = $offer->getItems()->count();
        $this->assertGreaterThan(1, $countBefore);

        $crawler = $client->request('GET', '/offers/' . $offer->getId() . '/edit');
        $this->assertResponseIsSuccessful();

        $form = $crawler->filter('form[name="offer"]')->form();
        $values = $form->getPhpValues();

        $laborIndex = null;
        foreach ($values['offer']['items'] as $idx => $item) {
            if (($item['type'] ?? null) === 'labor') {
                $laborIndex = $idx;
                break;
            }
        }
        $this->assertNotNull($laborIndex);

        unset($values['offer']['items'][$laborIndex]);

        $client->request($form->getMethod(), $form->getUri(), $values);
        $this->assertResponseRedirects('/offers/' . $offer->getId());

        $this->em()->clear();
        $reloaded = $this->repo()->findOneBy(['number' => $offer->getNumber()]);
        $this->assertSame($countBefore - 1, $reloaded->getItems()->count());
        foreach ($reloaded->getItems() as $item) {
            $this->assertNotNull($item->getType());
        }
    }

    public function testEditDoesNotCrashOnAllEmptyNewRow(): void
    {
        $client = $this->loginClient();
        $offer = $this->createTestOffer('OF-IT-EMPTY-PART');
        $countBefore = $offer->getItems()->count();

        $crawler = $client->request('GET', '/offers/' . $offer->getId() . '/edit');
        $form = $crawler->filter('form[name="offer"]')->form();
        $values = $form->getPhpValues();

        $values['offer']['items'][] = [
            'name' => '',
            'quantity' => '',
            'unitPrice' => '',
            'discountAmount' => '',
            'taxRate' => '',
            'type' => 'part',
            'sortOrder' => '88',
            'part' => '',
            'costPrice' => '',
        ];

        $client->request($form->getMethod(), $form->getUri(), $values);
        $this->assertNotSame(500, $client->getResponse()->getStatusCode(),
            'Completely empty new row must not crash.');

        $this->em()->clear();
        $reloaded = $this->repo()->findOneBy(['number' => $offer->getNumber()]);
        $this->assertSame($countBefore, $reloaded->getItems()->count());
    }

    public function testEditPartialRowFailsValidationNotTypeError(): void
    {
        $client = $this->loginClient();
        $offer = $this->createTestOffer('OF-IT-PARTIAL');
        $countBefore = $offer->getItems()->count();

        $crawler = $client->request('GET', '/offers/' . $offer->getId() . '/edit');
        $form = $crawler->filter('form[name="offer"]')->form();
        $values = $form->getPhpValues();

        $values['offer']['items'][] = [
            'name' => 'Half-typed labor',
            'quantity' => '',
            'unitPrice' => '',
            'discountAmount' => '',
            'taxRate' => '',
            'type' => 'labor',
            'sortOrder' => '99',
        ];

        $client->request($form->getMethod(), $form->getUri(), $values);

        $this->assertNotSame(500, $client->getResponse()->getStatusCode());
        $this->assertSame(422, $client->getResponse()->getStatusCode(),
            'Partial row should produce a validation re-render (HTTP 422).');

        $this->em()->clear();
        $reloaded = $this->repo()->findOneBy(['number' => $offer->getNumber()]);
        $this->assertSame($countBefore, $reloaded->getItems()->count());
    }

    public function testEditDropsRowsWithEmptyType(): void
    {
        $client = $this->loginClient();
        $offer = $this->createTestOffer('OF-IT-PHANTOM');
        $countBefore = $offer->getItems()->count();

        $crawler = $client->request('GET', '/offers/' . $offer->getId() . '/edit');
        $form = $crawler->filter('form[name="offer"]')->form();
        $values = $form->getPhpValues();

        $values['offer']['items'][] = [
            'name' => 'Phantom row',
            'quantity' => '1',
            'unitPrice' => '0',
            'discountAmount' => '0',
            'taxRate' => '0',
            'type' => '',
            'sortOrder' => '99',
        ];

        $client->request($form->getMethod(), $form->getUri(), $values);
        $this->assertNotSame(500, $client->getResponse()->getStatusCode(),
            'Empty-type rows must be dropped silently, not 500.');
        $this->assertResponseRedirects('/offers/' . $offer->getId());

        $this->em()->clear();
        $reloaded = $this->repo()->findOneBy(['number' => $offer->getNumber()]);
        $this->assertSame($countBefore, $reloaded->getItems()->count());
        foreach ($reloaded->getItems() as $item) {
            $this->assertNotSame('Phantom row', $item->getName());
        }
    }

    public function testEditAcceptsNewLaborRow(): void
    {
        $client = $this->loginClient();
        $offer = $this->createTestOffer('OF-IT-NEW-LABOR');
        $countBefore = $offer->getItems()->count();

        $crawler = $client->request('GET', '/offers/' . $offer->getId() . '/edit');
        $form = $crawler->filter('form[name="offer"]')->form();
        $values = $form->getPhpValues();

        $values['offer']['items'][] = [
            'name' => 'New labor line',
            'quantity' => '1',
            'unitPrice' => '99.00',
            'discountAmount' => '0',
            'taxRate' => '0',
            'type' => 'labor',
            'sortOrder' => '88',
        ];

        $client->request($form->getMethod(), $form->getUri(), $values);
        $this->assertResponseRedirects('/offers/' . $offer->getId());

        $this->em()->clear();
        $reloaded = $this->repo()->findOneBy(['number' => $offer->getNumber()]);
        $this->assertSame($countBefore + 1, $reloaded->getItems()->count());

        $found = null;
        foreach ($reloaded->getItems() as $item) {
            if ($item->getName() === 'New labor line') {
                $found = $item;
                break;
            }
        }
        $this->assertNotNull($found);
        $this->assertSame(OfferItemKind::Labor, $found->getType());
    }

    private function createTestOffer(string $numberSuffix): Offer
    {
        $em = $this->em();
        $client = $em->getRepository(Client::class)->findOneBy([]);
        $car = $em->getRepository(Car::class)->findOneBy(['client' => $client]);
        $this->assertNotNull($client);
        $this->assertNotNull($car);

        $offer = new Offer();
        $offer->setNumber($numberSuffix . '-' . substr(uniqid(), -4));
        $offer->setClient($client);
        $offer->setCar($car);

        $defaults = [
            ['Labor A', OfferItemKind::Labor, '40.00'],
            ['Part A', OfferItemKind::Part, '15.00'],
        ];
        foreach ($defaults as $i => [$name, $type, $price]) {
            $item = new OfferItem();
            $item->setType($type);
            $item->setName($name);
            $item->setQuantity('1.00');
            $item->setUnitPrice($price);
            $item->setSortOrder($i);
            $offer->addItem($item);
        }

        $em->persist($offer);
        $em->flush();
        $em->clear();

        return $this->repo()->findOneBy(['number' => $offer->getNumber()]);
    }

    private function repo(): OfferRepository
    {
        return static::getContainer()->get(OfferRepository::class);
    }

    private function em(): EntityManagerInterface
    {
        return static::getContainer()->get('doctrine')->getManager();
    }
}
