<?php

namespace App\Tests\Functional\Controller;

use App\Entity\Workshop\WorkOrder;
use App\Enum\WorkOrderStatus;
use App\Repository\UserRepository;
use App\Repository\Workshop\WorkOrderRepository;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class WorkOrderControllerTest extends WebTestCase
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
        $client->request('GET', '/work-orders');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h1', 'Work Orders');
    }

    public function testIndexShowsWorkOrders(): void
    {
        $client = $this->loginClient();
        $client->request('GET', '/work-orders');

        $this->assertSelectorExists('table');
        $this->assertSelectorTextContains('table', 'WO-2026-0001');
    }

    public function testStatusFilter(): void
    {
        $client = $this->loginClient();
        $client->request('GET', '/work-orders?status=completed');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('table', 'WO-2026-0002');
    }

    public function testShowWorkOrder(): void
    {
        $client = $this->loginClient();
        $crawler = $client->request('GET', '/work-orders');

        $link = $crawler->filter('table a')->first()->link();
        $client->click($link);

        $this->assertResponseIsSuccessful();
    }

    public function testNewWorkOrderPageLoads(): void
    {
        $client = $this->loginClient();
        $client->request('GET', '/work-orders/new');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorExists('form');
    }

    public function testAnonymousCannotAccessNewWorkOrder(): void
    {
        $client = static::createClient();
        $client->request('GET', '/work-orders/new');

        $this->assertResponseRedirects('/login');
    }

    public function testNewWorkOrderFormDoesNotExposeOwnerField(): void
    {
        $client = $this->loginClient();
        $crawler = $client->request('GET', '/work-orders/new');

        $this->assertResponseIsSuccessful();
        $this->assertCount(0, $crawler->filter('select[name="work_order[createdBy]"], input[name="work_order[createdBy]"]'));
    }

    public function testCreatedWorkOrderIsOwnedByAuthenticatedUser(): void
    {
        $client = $this->loginClient('mechanic@carstio.local');
        $crawler = $client->request('GET', '/work-orders/new');
        $this->assertResponseIsSuccessful();

        $workOrderRepository = static::getContainer()->get(WorkOrderRepository::class);
        $existingIds = array_map(fn (WorkOrder $wo) => $wo->getId(), $workOrderRepository->findAll());

        $clientRepo = static::getContainer()->get('doctrine')->getRepository(\App\Entity\Client\Client::class);
        $carRepo = static::getContainer()->get('doctrine')->getRepository(\App\Entity\Car\Car::class);
        $clientEntity = $clientRepo->findOneBy(['name' => 'John Doe']);
        $carEntity = $carRepo->findOneBy(['client' => $clientEntity]);
        $this->assertNotNull($clientEntity);
        $this->assertNotNull($carEntity);

        $form = $crawler->filter('form[name="work_order"]')->form();
        $form['work_order[client]'] = (string) $clientEntity->getId();
        $form['work_order[car]'] = (string) $carEntity->getId();
        $form['work_order[complaint]'] = 'Test complaint for ownership check';

        $client->submit($form);

        $this->assertResponseRedirects();

        $created = null;
        foreach ($workOrderRepository->findAll() as $wo) {
            if (!in_array($wo->getId(), $existingIds, true)) {
                $created = $wo;
                break;
            }
        }

        $this->assertNotNull($created, 'A new work order should have been created.');
        $this->assertNotNull($created->getCreatedBy());
        $this->assertSame('mechanic@carstio.local', $created->getCreatedBy()->getEmail());
    }

    public function testEditAcceptsItemsWithEmptyDiscountAndTaxRate(): void
    {
        $client = $this->loginClient();

        $workOrderRepository = static::getContainer()->get(WorkOrderRepository::class);
        $workOrder = $workOrderRepository->findOneBy(['number' => 'WO-2026-0001']);
        $this->assertNotNull($workOrder);
        $this->assertGreaterThan(0, $workOrder->getItems()->count());

        $crawler = $client->request('GET', '/work-orders/' . $workOrder->getId() . '/edit');
        $this->assertResponseIsSuccessful();

        $form = $crawler->filter('form[name="work_order"]')->form();
        $values = $form->getPhpValues();

        $this->assertArrayHasKey('items', $values['work_order']);
        $cleared = 0;
        foreach ($values['work_order']['items'] as $index => $item) {
            $values['work_order']['items'][$index]['discountAmount'] = '';
            $values['work_order']['items'][$index]['taxRate'] = '';
            ++$cleared;
        }
        $this->assertGreaterThan(0, $cleared, 'Fixture should include at least one item.');

        $client->request($form->getMethod(), $form->getUri(), $values);

        $this->assertResponseRedirects('/work-orders/' . $workOrder->getId());

        static::getContainer()->get('doctrine')->getManager()->clear();
        $workOrder = $workOrderRepository->findOneBy(['number' => 'WO-2026-0001']);
        $this->assertNotNull($workOrder);
        $this->assertSame('0.00', $workOrder->getDiscountAmount());
        $this->assertSame('0.00', $workOrder->getTaxAmount());

        foreach ($workOrder->getItems() as $item) {
            $this->assertSame('0.00', $item->getDiscountAmount());
            $this->assertSame('0.00', $item->getTaxRate());
        }
    }

    public function testChangingStatusThroughEditFormStampsLifecycleTimestamps(): void
    {
        $client = $this->loginClient();

        $workOrderRepository = static::getContainer()->get(WorkOrderRepository::class);
        $workOrder = $workOrderRepository->findOneBy(['number' => 'WO-2026-0001']);
        $this->assertNotNull($workOrder);
        $this->assertSame(WorkOrderStatus::InProgress, $workOrder->getStatus());
        $this->assertNull($workOrder->getCompletedAt());
        $startedAt = $workOrder->getStartedAt();

        $crawler = $client->request('GET', '/work-orders/' . $workOrder->getId() . '/edit');
        $form = $crawler->filter('form[name="work_order"]')->form();
        $form['work_order[status]'] = 'completed';
        $client->submit($form);

        $this->assertResponseRedirects('/work-orders/' . $workOrder->getId());

        static::getContainer()->get('doctrine')->getManager()->clear();
        $workOrder = $workOrderRepository->findOneBy(['number' => 'WO-2026-0001']);
        $this->assertSame(WorkOrderStatus::Completed, $workOrder->getStatus());
        $this->assertNotNull($workOrder->getCompletedAt(), 'Completing through the form must stamp completedAt like the status action does.');
        $this->assertEquals($startedAt, $workOrder->getStartedAt(), 'startedAt must be preserved.');
    }
}
