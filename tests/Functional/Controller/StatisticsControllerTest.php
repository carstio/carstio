<?php

declare(strict_types=1);

namespace App\Tests\Functional\Controller;

use App\Repository\UserRepository;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class StatisticsControllerTest extends WebTestCase
{
    private function loginAdmin(): KernelBrowser
    {
        $client = static::createClient();
        $user = static::getContainer()->get(UserRepository::class)->findOneBy(['email' => 'admin@carstio.local']);
        $client->loginUser($user);

        return $client;
    }

    private function loginMechanic(): KernelBrowser
    {
        $client = static::createClient();
        $user = static::getContainer()->get(UserRepository::class)->findOneBy(['email' => 'mechanic@carstio.local']);
        self::assertNotNull($user, 'Mechanic fixture user must exist for security tests.');
        $client->loginUser($user);

        return $client;
    }

    public function testAnonymousIsRedirectedToLogin(): void
    {
        $client = static::createClient();
        $client->request('GET', '/statistics');

        $this->assertResponseRedirects('/login');
    }

    public function testMechanicIsForbidden(): void
    {
        $client = $this->loginMechanic();
        $client->request('GET', '/statistics');

        $this->assertSame(403, $client->getResponse()->getStatusCode());
    }

    public function testAdminCanLoadDashboard(): void
    {
        $client = $this->loginAdmin();
        $client->request('GET', '/statistics');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h1', 'Statistics');
        $this->assertSelectorExists('.summary-grid .summary-tile');
    }

    public function testThisMonthPresetRendersKpis(): void
    {
        $client = $this->loginAdmin();
        $client->request('GET', '/statistics?range=this_month');

        $this->assertResponseIsSuccessful();
        // KPI labels should appear regardless of fixture amounts.
        $this->assertSelectorTextContains('.summary-grid', 'Total revenue');
        $this->assertSelectorTextContains('.summary-grid', 'Open work orders');
        $this->assertSelectorTextContains('.summary-grid', 'Offer conversion');
    }

    public function testCustomRangeWithInvalidDatesStillRenders(): void
    {
        $client = $this->loginAdmin();
        $client->request('GET', '/statistics?range=custom&from=2026-04-30&to=2026-04-01');

        // Inverted range should not 500 - it falls back to the default preset.
        $this->assertResponseIsSuccessful();
    }

    public function testPartsProfitKpiAppearsOnDashboard(): void
    {
        $client = $this->loginAdmin();
        $client->request('GET', '/statistics?range=this_month');

        $this->assertResponseIsSuccessful();
        // The new Parts KPI cards must surface in place of the dropped
        // low-stock table.
        $this->assertSelectorTextContains('.summary-grid', 'Parts revenue');
        $this->assertSelectorTextContains('.summary-grid', 'Parts profit');
    }

    public function testTopProfitablePartsTableSurfacesFixturePartName(): void
    {
        $client = $this->loginAdmin();
        $client->request('GET', '/statistics?range=this_month');

        $this->assertResponseIsSuccessful();
        // Fixtures sell an "Oil Filter" with cost=3.50 / sell=8.00 on
        // WO-2026-0001, so it must show up in the top profitable parts table.
        $this->assertSelectorTextContains('body', 'Oil Filter');
    }
}
