<?php

namespace App\Tests\Functional\Controller;

use App\Repository\UserRepository;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\AssetMapper\AssetMapperInterface;

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

    /**
     * The calendar's library is not installed through the import map but
     * committed (assets/lib/README.md). If the dashboard pointed at a file
     * that only exists on a developer's machine, this fails on a fresh
     * checkout - which is how the calendar once went missing everywhere else.
     */
    public function testTheCalendarLibraryIsPartOfTheApplication(): void
    {
        $client = static::createClient();
        $user = static::getContainer()->get(UserRepository::class)->findOneBy(['email' => 'admin@carstio.local']);
        $client->loginUser($user);
        $crawler = $client->request('GET', '/');

        $url = (string) $crawler->filter('[data-controller="calendar"]')->attr('data-calendar-bundle-url-value');
        $this->assertMatchesRegularExpression('#^/assets/lib/fullcalendar/fullcalendar\.global\.min-[A-Za-z0-9_-]+\.js$#', $url);

        $asset = static::getContainer()->get(AssetMapperInterface::class)->getAsset('lib/fullcalendar/fullcalendar.global.min.js');
        $this->assertNotNull($asset);
        $this->assertSame($asset->publicPath, $url, 'The address on the dashboard is one the application serves.');
        $this->assertFileExists($asset->sourcePath);
        $this->assertStringContainsString('FullCalendar Standard Bundle', (string) file_get_contents($asset->sourcePath, false, null, 0, 200));
        $this->assertStringContainsString('/assets/lib/', str_replace(\DIRECTORY_SEPARATOR, '/', $asset->sourcePath), 'Committed, not in the downloaded vendor directory.');
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
