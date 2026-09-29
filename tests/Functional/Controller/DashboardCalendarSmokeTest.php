<?php

namespace App\Tests\Functional\Controller;

use App\Repository\UserRepository;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class DashboardCalendarSmokeTest extends WebTestCase
{
    public function testDashboardHtmlContainsCalendarDiv(): void
    {
        $client = static::createClient();
        $user = static::getContainer()->get(UserRepository::class)->findOneBy(['email' => 'admin@carstio.local']);
        $client->loginUser($user);
        $client->request('GET', '/');
        $html = $client->getResponse()->getContent();
        $this->assertStringContainsString('data-controller="calendar"', $html);
    }

    public function testCalendarEventsEndpointReturnsJson(): void
    {
        $client = static::createClient();
        $user = static::getContainer()->get(UserRepository::class)->findOneBy(['email' => 'admin@carstio.local']);
        $client->loginUser($user);
        $client->request('GET', '/dashboard/calendar/events');
        $this->assertResponseIsSuccessful();
        $this->assertJson($client->getResponse()->getContent());
    }
}
