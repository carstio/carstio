<?php

namespace App\Tests\Functional\Controller;

use App\Repository\UserRepository;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class SearchControllerTest extends WebTestCase
{
    private function loginClient(): KernelBrowser
    {
        $client = static::createClient();
        $user = static::getContainer()->get(UserRepository::class)->findOneBy(['email' => 'admin@carstio.local']);
        $client->loginUser($user);

        return $client;
    }

    public function testUnauthenticatedRequestRedirectsToLogin(): void
    {
        $client = static::createClient();
        $client->request('GET', '/search/autocomplete?q=alice');

        $this->assertResponseRedirects('/login');
    }

    public function testQueryBelowMinLengthReturnsEmptyGroups(): void
    {
        $client = $this->loginClient();
        $client->request('GET', '/search/autocomplete?q=a');

        $this->assertResponseIsSuccessful();
        $this->assertResponseHeaderSame('Content-Type', 'application/json');

        $data = $this->decodeJson($client->getResponse()->getContent());
        $this->assertSame('a', $data['query']);
        $this->assertSame([], $data['groups']);
    }

    public function testEmptyQueryReturnsEmptyGroups(): void
    {
        $client = $this->loginClient();
        $client->request('GET', '/search/autocomplete?q=');

        $this->assertResponseIsSuccessful();

        $data = $this->decodeJson($client->getResponse()->getContent());
        $this->assertSame('', $data['query']);
        $this->assertSame([], $data['groups']);
    }

    public function testClientSearchReturnsMatchingClient(): void
    {
        $client = $this->loginClient();
        $client->request('GET', '/search/autocomplete?q=alice');

        $this->assertResponseIsSuccessful();
        $data = $this->decodeJson($client->getResponse()->getContent());

        $clientGroup = $this->groupByType($data, 'client');
        $this->assertNotEmpty($clientGroup['items'], 'Expected at least one client item.');

        $labels = array_column($clientGroup['items'], 'label');
        $this->assertContains('Alice Smith', $labels);

        $alice = $this->itemByLabel($clientGroup['items'], 'Alice Smith');
        $this->assertStringStartsWith('/clients/', $alice['url']);
        $this->assertNotSame('', $alice['description']);
    }

    public function testCarSearchReturnsBrandModelAndShowUrl(): void
    {
        $client = $this->loginClient();
        $client->request('GET', '/search/autocomplete?q=corolla');

        $this->assertResponseIsSuccessful();
        $data = $this->decodeJson($client->getResponse()->getContent());

        $carGroup = $this->groupByType($data, 'car');
        $this->assertNotEmpty($carGroup['items'], 'Expected at least one car item.');

        $first = $carGroup['items'][0];
        $this->assertStringContainsString('Corolla', $first['label']);
        $this->assertStringStartsWith('/cars/', $first['url']);
    }

    public function testCompanySearchReturnsMatchingCompany(): void
    {
        $client = $this->loginClient();
        $client->request('GET', '/search/autocomplete?q=doe%20auto');

        $this->assertResponseIsSuccessful();
        $data = $this->decodeJson($client->getResponse()->getContent());

        $companyGroup = $this->groupByType($data, 'company');
        $this->assertNotEmpty($companyGroup['items'], 'Expected at least one company item.');

        $labels = array_column($companyGroup['items'], 'label');
        $this->assertContains('Doe Auto Services Ltd', $labels);

        $first = $this->itemByLabel($companyGroup['items'], 'Doe Auto Services Ltd');
        $this->assertStringStartsWith('/companies/', $first['url']);
    }

    public function testJsonStructureIsStable(): void
    {
        $client = $this->loginClient();
        $client->request('GET', '/search/autocomplete?q=alice');

        $data = $this->decodeJson($client->getResponse()->getContent());
        $this->assertArrayHasKey('query', $data);
        $this->assertArrayHasKey('groups', $data);
        $this->assertCount(3, $data['groups']);

        $types = array_column($data['groups'], 'type');
        $this->assertSame(['client', 'car', 'company'], $types);

        foreach ($data['groups'] as $group) {
            $this->assertArrayHasKey('label', $group);
            $this->assertArrayHasKey('items', $group);
            foreach ($group['items'] as $item) {
                $this->assertArrayHasKey('label', $item);
                $this->assertArrayHasKey('description', $item);
                $this->assertArrayHasKey('url', $item);
            }
        }
    }

    public function testHeaderRendersSearchInputForAuthenticatedUsers(): void
    {
        $client = $this->loginClient();
        $crawler = $client->request('GET', '/');

        $this->assertResponseIsSuccessful();

        $wrapper = $crawler->filter('.global-search[data-controller="global-search"]');
        $this->assertCount(1, $wrapper, 'Header must render the global-search Stimulus wrapper.');
        $this->assertSame('/search/autocomplete', $wrapper->attr('data-global-search-url-value'));

        $this->assertCount(1, $crawler->filter('.global-search input[data-global-search-target="input"]'));
        $this->assertCount(1, $crawler->filter('.global-search [data-global-search-target="results"]'));

        $icon = $crawler->filter('.global-search .global-search-input-wrap > .global-search-icon svg');
        $this->assertCount(1, $icon, 'Search icon SVG must render inside the input wrapper.');
    }

    private function decodeJson(string|false $body): array
    {
        $this->assertNotFalse($body, 'Empty response body.');

        return json_decode($body, true, 512, JSON_THROW_ON_ERROR);
    }

    private function groupByType(array $data, string $type): array
    {
        foreach ($data['groups'] as $group) {
            if ($group['type'] === $type) {
                return $group;
            }
        }

        $this->fail(sprintf('Group "%s" not found in response.', $type));
    }

    private function itemByLabel(array $items, string $label): array
    {
        foreach ($items as $item) {
            if ($item['label'] === $label) {
                return $item;
            }
        }

        $this->fail(sprintf('Item "%s" not found.', $label));
    }
}
