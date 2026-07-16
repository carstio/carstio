<?php

namespace App\Tests\Functional\Controller;

use App\Entity\Car\Car;
use App\Entity\Client\Client;
use App\Entity\Workshop\WorkOrder;
use App\Entity\Workshop\WorkOrderItem;
use App\Enum\WorkOrderItemType as WorkOrderItemKind;
use App\Repository\UserRepository;
use App\Repository\Workshop\WorkOrderRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Covers WorkOrder edit collection handling: deletion, addition, and the
 * "phantom row with empty type" case the JS controller used to produce.
 *
 * Each test builds its own throwaway WorkOrder so it cannot pollute the
 * shared fixture work orders that other tests depend on.
 */
class WorkOrderItemDeletionTest extends WebTestCase
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
        $workOrder = $this->createTestWorkOrder('WO-IT-DEL-LABOR');
        $countBefore = $workOrder->getItems()->count();
        $this->assertGreaterThan(1, $countBefore);

        $crawler = $client->request('GET', '/work-orders/' . $workOrder->getId() . '/edit');
        $this->assertResponseIsSuccessful();

        $form = $crawler->filter('form[name="work_order"]')->form();
        $values = $form->getPhpValues();

        $laborIndex = null;
        $laborName = null;
        foreach ($values['work_order']['items'] as $idx => $item) {
            if (($item['type'] ?? null) === 'labor') {
                $laborIndex = $idx;
                $laborName = $item['name'] ?? null;
                break;
            }
        }
        $this->assertNotNull($laborIndex);

        unset($values['work_order']['items'][$laborIndex]);

        $client->request($form->getMethod(), $form->getUri(), $values);
        $this->assertResponseRedirects('/work-orders/' . $workOrder->getId());

        $this->em()->clear();
        $reloaded = $this->repo()->findOneBy(['number' => $workOrder->getNumber()]);
        $this->assertSame($countBefore - 1, $reloaded->getItems()->count());
        foreach ($reloaded->getItems() as $item) {
            $this->assertNotSame($laborName, $item->getName());
            $this->assertNotNull($item->getType());
        }
    }

    public function testEditCanRemovePartItemAndSubmit(): void
    {
        $client = $this->loginClient();
        $workOrder = $this->createTestWorkOrder('WO-IT-DEL-PART');
        $countBefore = $workOrder->getItems()->count();

        $crawler = $client->request('GET', '/work-orders/' . $workOrder->getId() . '/edit');
        $form = $crawler->filter('form[name="work_order"]')->form();
        $values = $form->getPhpValues();

        $partIndex = null;
        foreach ($values['work_order']['items'] as $idx => $item) {
            if (($item['type'] ?? null) === 'part') {
                $partIndex = $idx;
                break;
            }
        }
        $this->assertNotNull($partIndex);

        unset($values['work_order']['items'][$partIndex]);

        $client->request($form->getMethod(), $form->getUri(), $values);
        $this->assertResponseRedirects('/work-orders/' . $workOrder->getId());

        $this->em()->clear();
        $reloaded = $this->repo()->findOneBy(['number' => $workOrder->getNumber()]);
        $this->assertSame($countBefore - 1, $reloaded->getItems()->count());
    }

    public function testEditDoesNotCrashOnAllEmptyNewRow(): void
    {
        // User added a row via "Add part" then submitted without filling
        // anything. The JS sets `type` so the empty-type filter cannot help.
        // The form must still survive without a TypeError on setQuantity(null).
        $client = $this->loginClient();
        $workOrder = $this->createTestWorkOrder('WO-IT-EMPTY-PART');
        $countBefore = $workOrder->getItems()->count();

        $crawler = $client->request('GET', '/work-orders/' . $workOrder->getId() . '/edit');
        $form = $crawler->filter('form[name="work_order"]')->form();
        $values = $form->getPhpValues();

        $values['work_order']['items'][] = [
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

        $status = $client->getResponse()->getStatusCode();
        $this->assertNotSame(500, $status,
            'A completely empty new row must not crash with a PHP TypeError.');

        $this->em()->clear();
        $reloaded = $this->repo()->findOneBy(['number' => $workOrder->getNumber()]);
        $this->assertSame($countBefore, $reloaded->getItems()->count(),
            'Truly empty rows must not be persisted.');
    }

    public function testEditPartialRowFailsValidationNotTypeError(): void
    {
        // User started typing a name but forgot to fill quantity / unit price.
        // The form must show validation errors, not 500.
        $client = $this->loginClient();
        $workOrder = $this->createTestWorkOrder('WO-IT-PARTIAL');
        $countBefore = $workOrder->getItems()->count();

        $crawler = $client->request('GET', '/work-orders/' . $workOrder->getId() . '/edit');
        $form = $crawler->filter('form[name="work_order"]')->form();
        $values = $form->getPhpValues();

        $values['work_order']['items'][] = [
            'name' => 'Half-typed labor',
            'quantity' => '',
            'unitPrice' => '',
            'discountAmount' => '',
            'taxRate' => '',
            'type' => 'labor',
            'sortOrder' => '99',
        ];

        $client->request($form->getMethod(), $form->getUri(), $values);

        $this->assertNotSame(500, $client->getResponse()->getStatusCode(),
            'Partially filled rows must not crash.');
        // Form should NOT redirect (would be 302). It should re-render the
        // edit page with validation errors. Symfony auto-sets 422 in that
        // case via the form-render listener.
        $this->assertSame(422, $client->getResponse()->getStatusCode(),
            'Partial row should produce a validation re-render (HTTP 422).');

        $this->em()->clear();
        $reloaded = $this->repo()->findOneBy(['number' => $workOrder->getNumber()]);
        $this->assertSame($countBefore, $reloaded->getItems()->count(),
            'Invalid row must not be persisted.');
    }

    public function testEditDropsRowsWithEmptyType(): void
    {
        // Phantom row with empty type (the JS used to emit these). The form
        // must filter them out (PRE_SUBMIT listener) so we don't 500 on the
        // non-nullable WorkOrderItem::setType() call.
        $client = $this->loginClient();
        $workOrder = $this->createTestWorkOrder('WO-IT-PHANTOM');
        $countBefore = $workOrder->getItems()->count();

        $crawler = $client->request('GET', '/work-orders/' . $workOrder->getId() . '/edit');
        $form = $crawler->filter('form[name="work_order"]')->form();
        $values = $form->getPhpValues();

        $values['work_order']['items'][] = [
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
        $this->assertResponseRedirects('/work-orders/' . $workOrder->getId());

        $this->em()->clear();
        $reloaded = $this->repo()->findOneBy(['number' => $workOrder->getNumber()]);
        $this->assertSame($countBefore, $reloaded->getItems()->count(),
            'Phantom row must not have been persisted.');
        foreach ($reloaded->getItems() as $item) {
            $this->assertNotNull($item->getType());
            $this->assertNotSame('Phantom row', $item->getName());
        }
    }

    public function testEditAcceptsNewLaborRow(): void
    {
        $client = $this->loginClient();
        $workOrder = $this->createTestWorkOrder('WO-IT-NEW-LABOR');
        $countBefore = $workOrder->getItems()->count();

        $crawler = $client->request('GET', '/work-orders/' . $workOrder->getId() . '/edit');
        $form = $crawler->filter('form[name="work_order"]')->form();
        $values = $form->getPhpValues();

        $values['work_order']['items'][] = [
            'name' => 'Diagnostics labor',
            'quantity' => '2',
            'unitPrice' => '40.00',
            'discountAmount' => '0',
            'taxRate' => '0',
            'type' => 'labor',
            'sortOrder' => '88',
        ];

        $client->request($form->getMethod(), $form->getUri(), $values);
        $this->assertResponseRedirects('/work-orders/' . $workOrder->getId());

        $this->em()->clear();
        $reloaded = $this->repo()->findOneBy(['number' => $workOrder->getNumber()]);
        $this->assertSame($countBefore + 1, $reloaded->getItems()->count());

        $found = null;
        foreach ($reloaded->getItems() as $item) {
            if ($item->getName() === 'Diagnostics labor') {
                $found = $item;
                break;
            }
        }
        $this->assertNotNull($found);
        $this->assertSame(WorkOrderItemKind::Labor, $found->getType());
    }

    public function testEditCanClearAllItems(): void
    {
        $client = $this->loginClient();
        $workOrder = $this->createTestWorkOrder('WO-IT-CLEAR');
        $this->assertGreaterThan(0, $workOrder->getItems()->count());

        $crawler = $client->request('GET', '/work-orders/' . $workOrder->getId() . '/edit');
        $form = $crawler->filter('form[name="work_order"]')->form();
        $values = $form->getPhpValues();
        $values['work_order']['items'] = [];

        $client->request($form->getMethod(), $form->getUri(), $values);
        $this->assertResponseRedirects('/work-orders/' . $workOrder->getId());

        $this->em()->clear();
        $reloaded = $this->repo()->findOneBy(['number' => $workOrder->getNumber()]);
        $this->assertSame(0, $reloaded->getItems()->count());
    }

    /**
     * Creates a throwaway WorkOrder owned by the first available client+car
     * pair. Each test passes a unique number suffix to keep WOs distinct.
     */
    private function createTestWorkOrder(string $numberSuffix): WorkOrder
    {
        $em = $this->em();
        $client = $em->getRepository(Client::class)->findOneBy([]);
        $car = $em->getRepository(Car::class)->findOneBy(['client' => $client]);
        $this->assertNotNull($client);
        $this->assertNotNull($car);

        $workOrder = new WorkOrder();
        $workOrder->setNumber($numberSuffix . '-' . substr(uniqid(), -4));
        $workOrder->setClient($client);
        $workOrder->setCar($car);
        $workOrder->setComplaint('Test complaint');

        $defaults = [
            ['Labor A', WorkOrderItemKind::Labor, '50.00'],
            ['Part A', WorkOrderItemKind::Part, '20.00'],
            ['Part B', WorkOrderItemKind::Part, '15.00'],
        ];
        foreach ($defaults as $i => [$name, $type, $price]) {
            $item = new WorkOrderItem();
            $item->setType($type);
            $item->setName($name);
            $item->setQuantity('1.00');
            $item->setUnitPrice($price);
            $item->setSortOrder($i);
            $workOrder->addItem($item);
        }

        $em->persist($workOrder);
        $em->flush();
        $em->clear();

        return $this->repo()->findOneBy(['number' => $workOrder->getNumber()]);
    }

    private function repo(): WorkOrderRepository
    {
        return static::getContainer()->get(WorkOrderRepository::class);
    }

    private function em(): EntityManagerInterface
    {
        return static::getContainer()->get('doctrine')->getManager();
    }
}
