<?php

namespace App\Tests\Functional\Controller;

use App\Repository\UserRepository;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class CompanyControllerTest extends WebTestCase
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
        $client->request('GET', '/companies');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h1', 'Companies');
    }

    public function testNewCompanyPageLoads(): void
    {
        $client = $this->loginClient();
        $client->request('GET', '/companies/new');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorExists('form');
    }
}
