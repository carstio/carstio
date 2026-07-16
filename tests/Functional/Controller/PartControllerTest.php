<?php

namespace App\Tests\Functional\Controller;

use App\Repository\Part\PartRepository;
use App\Repository\UserRepository;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class PartControllerTest extends WebTestCase
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
        $client->request('GET', '/parts');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h1', 'Parts');
    }

    public function testIndexShowsParts(): void
    {
        $client = $this->loginClient();
        $client->request('GET', '/parts');

        $this->assertSelectorExists('table');
        $this->assertSelectorTextContains('table', 'Oil Filter');
    }

    public function testIndexDoesNotShowStockColumns(): void
    {
        $client = $this->loginClient();
        $crawler = $client->request('GET', '/parts');

        $headers = $crawler->filter('table thead th')->each(fn ($th) => trim($th->text()));

        // Stock-related columns must be gone after the parts-resale rewrite.
        $this->assertNotContains('Stock', $headers);
        $this->assertNotContains('Min Stock', $headers);
        $this->assertContains('Cost Price', $headers);
        $this->assertContains('Sell Price', $headers);
    }

    public function testNewPartPageLoads(): void
    {
        $client = $this->loginClient();
        $crawler = $client->request('GET', '/parts/new');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorExists('form');

        // The form must not expose any of the dropped warehouse-style fields.
        $this->assertCount(0, $crawler->filter('input[name="part[stockQuantity]"]'));
        $this->assertCount(0, $crawler->filter('input[name="part[minStockLevel]"]'));
        $this->assertCount(0, $crawler->filter('input[name="part[location]"]'));
        $this->assertCount(0, $crawler->filter('input[name="part[barcode]"]'));
        $this->assertCount(0, $crawler->filter('input[name="part[unit]"]'));

        // SKU and prices must remain but optional (no `required` attribute).
        $sku = $crawler->filter('input[name="part[sku]"]');
        $this->assertCount(1, $sku);
        $this->assertSame('', (string) $sku->attr('required'));
    }

    public function testCreatePartWithOnlyNameSucceeds(): void
    {
        $client = $this->loginClient();
        $crawler = $client->request('GET', '/parts/new');
        $form = $crawler->selectButton('Save')->form();

        $unique = 'TEST-MIN-' . uniqid();
        $form['part[name]'] = $unique;
        // Cost / sell / sku intentionally left blank - must save.

        $client->submit($form);

        $this->assertResponseRedirects();

        $repo = static::getContainer()->get(PartRepository::class);
        $part = $repo->findOneBy(['name' => $unique]);
        $this->assertNotNull($part, 'Part must persist with only a name.');
        $this->assertNull($part->getCostPrice());
        $this->assertNull($part->getSellPrice());
        $this->assertNull($part->getSku());
    }
}
