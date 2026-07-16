<?php

namespace App\Tests\Functional\Controller;

use App\Entity\Workshop\WorkOrder;
use App\Repository\UserRepository;
use App\Repository\Workshop\WorkOrderRepository;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class WorkOrderPaymentsTabTest extends WebTestCase
{
    private function loginClient(): KernelBrowser
    {
        $client = static::createClient();
        $user = static::getContainer()->get(UserRepository::class)->findOneBy(['email' => 'admin@carstio.local']);
        $client->loginUser($user);

        return $client;
    }

    private function workOrder(string $number): WorkOrder
    {
        $repo = static::getContainer()->get(WorkOrderRepository::class);
        $workOrder = $repo->findOneBy(['number' => $number]);
        $this->assertNotNull($workOrder);

        return $workOrder;
    }

    public function testEditPageRendersPaymentsTab(): void
    {
        $client = $this->loginClient();
        $workOrder = $this->workOrder('WO-2026-0001');

        $crawler = $client->request('GET', '/work-orders/' . $workOrder->getId() . '/edit');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorExists('button[data-bs-target="#pane-payments"]');
        $this->assertSelectorExists('div#pane-payments');
        $this->assertCount(1, $crawler->filter('a[href$="/work-orders/' . $workOrder->getId() . '/payments/new"]'));
    }

    public function testPaymentsTabListsOnlyPaymentsForCurrentWorkOrder(): void
    {
        $client = $this->loginClient();

        $woWithDeposit = $this->workOrder('WO-2026-0001');
        $crawler = $client->request('GET', '/work-orders/' . $woWithDeposit->getId() . '/edit');
        $this->assertResponseIsSuccessful();

        $pane = $crawler->filter('div#pane-payments');
        $this->assertCount(1, $pane);

        $rowCount = $pane->filter('tbody tr')->count();
        $this->assertSame($woWithDeposit->getPayments()->count(), $rowCount);

        $this->assertStringContainsString('CASH-20260411-001', $pane->text());
        $this->assertStringNotContainsString('CARD-20260410-001', $pane->text());
    }

    public function testPaymentsTabShowsCorrectSummary(): void
    {
        $client = $this->loginClient();
        $workOrder = $this->workOrder('WO-2026-0001');

        $crawler = $client->request('GET', '/work-orders/' . $workOrder->getId() . '/edit');
        $this->assertResponseIsSuccessful();

        $paneText = $crawler->filter('div#pane-payments')->text();

        // Total: 30 + 8 + 60 + 55 = 153.00; Paid: 50.00; Remaining: 103.00
        $this->assertStringContainsString('153.00', $paneText);
        $this->assertStringContainsString('50.00', $paneText);
        $this->assertStringContainsString('103.00', $paneText);
    }

    public function testPaymentsTabShowsZeroRemainingWhenFullyPaid(): void
    {
        $client = $this->loginClient();
        $workOrder = $this->workOrder('WO-2026-0002');

        $crawler = $client->request('GET', '/work-orders/' . $workOrder->getId() . '/edit');
        $this->assertResponseIsSuccessful();

        $paneText = $crawler->filter('div#pane-payments')->text();

        // Total: 45 (labor) + 4*9.50 (spark plugs) + 4*7.50 (engine oil free-form) = 113.00
        // Paid: 113.00; Remaining: 0.00
        $this->assertStringContainsString('113.00', $paneText);
        $this->assertStringContainsString('0.00', $paneText);
    }

    public function testAddPaymentLinkUsesCurrentWorkOrderRoute(): void
    {
        $client = $this->loginClient();
        $workOrder = $this->workOrder('WO-2026-0001');

        $crawler = $client->request('GET', '/work-orders/' . $workOrder->getId() . '/edit');
        $this->assertResponseIsSuccessful();

        $expected = '/work-orders/' . $workOrder->getId() . '/payments/new';
        $this->assertCount(
            1,
            $crawler->filter('div#pane-payments a[href="' . $expected . '"]'),
        );
    }

    public function testEditFormStillSubmitsAfterTabsChange(): void
    {
        $client = $this->loginClient();
        $workOrder = $this->workOrder('WO-2026-0001');

        $crawler = $client->request('GET', '/work-orders/' . $workOrder->getId() . '/edit');
        $this->assertResponseIsSuccessful();

        $form = $crawler->filter('form[name="work_order"]')->form();
        $client->submit($form);

        $this->assertResponseRedirects('/work-orders/' . $workOrder->getId());
    }

    public function testPaymentsTabHasNoNestedForms(): void
    {
        $client = $this->loginClient();
        $workOrder = $this->workOrder('WO-2026-0001');

        $crawler = $client->request('GET', '/work-orders/' . $workOrder->getId() . '/edit');
        $this->assertResponseIsSuccessful();

        $this->assertCount(0, $crawler->filter('div#pane-payments form'));
    }
}
