<?php

namespace App\Tests\Functional\Controller;

use App\Repository\UserRepository;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class CarControllerTest extends WebTestCase
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
        $client->request('GET', '/cars');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h1', 'Cars');
    }

    public function testIndexShowsCars(): void
    {
        $client = $this->loginClient();
        $client->request('GET', '/cars');

        $this->assertSelectorExists('table');
        $this->assertSelectorTextContains('table', 'Toyota Corolla');
    }

    public function testNewCarPageLoads(): void
    {
        $client = $this->loginClient();
        $client->request('GET', '/cars/new');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorExists('form');
    }
}
