<?php

namespace App\Tests\Functional\Controller;

use App\Entity\Workshop\Payment;
use App\Entity\Workshop\WorkOrder;
use App\Repository\UserRepository;
use App\Repository\Workshop\PaymentRepository;
use App\Repository\Workshop\WorkOrderRepository;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class PaymentControllerTest extends WebTestCase
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
        $client->request('GET', '/payments');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h1', 'Payments');
    }

    public function testIndexShowsPayments(): void
    {
        $client = $this->loginClient();
        $client->request('GET', '/payments');

        $this->assertSelectorExists('table');
    }

    public function testNewPaymentPageLoads(): void
    {
        $client = $this->loginClient();
        $client->request('GET', '/payments/new');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorExists('form');
    }

    public function testPaidAtFieldRendersAsFlatpickrTextInput(): void
    {
        $client = $this->loginClient();
        $crawler = $client->request('GET', '/payments/new');

        $this->assertResponseIsSuccessful();
        $input = $crawler->filter('input[name="payment[paidAt]"]');
        $this->assertCount(1, $input);
        $this->assertSame('text', $input->attr('type'));
        $this->assertStringContainsString('datetime-field', (string) $input->attr('data-controller'));
        $this->assertSame('true', $input->attr('data-datetime-field-now-value'));
        $this->assertSame('off', $input->attr('autocomplete'));
    }

    public function testSubmittedPaidAtIsPersistedAsDateTimeImmutable(): void
    {
        $client = $this->loginClient();

        $workOrderRepo = static::getContainer()->get(WorkOrderRepository::class);
        $paymentRepo = static::getContainer()->get(PaymentRepository::class);
        $workOrder = $workOrderRepo->findOneBy(['number' => 'WO-2026-0001']);
        $this->assertNotNull($workOrder);

        $existingIds = array_map(fn (Payment $p) => $p->getId(), $paymentRepo->findAll());

        $crawler = $client->request('GET', '/work-orders/' . $workOrder->getId() . '/payments/new');
        $this->assertResponseIsSuccessful();

        $form = $crawler->filter('form')->form();
        $form['payment[amount]'] = '42.50';
        $form['payment[method]'] = 'cash';
        $form['payment[paidAt]'] = '2026-04-22 09:15';

        $client->submit($form);

        $this->assertResponseRedirects();

        $created = null;
        foreach ($paymentRepo->findAll() as $payment) {
            if (!in_array($payment->getId(), $existingIds, true)) {
                $created = $payment;
                break;
            }
        }

        $this->assertNotNull($created, 'A new payment should have been created.');
        $this->assertInstanceOf(\DateTimeImmutable::class, $created->getPaidAt());
        $this->assertSame('2026-04-22 09:15', $created->getPaidAt()->format('Y-m-d H:i'));
    }

    public function testWorkOrderScopedNewPrefillsWorkOrder(): void
    {
        $client = $this->loginClient();
        $workOrder = static::getContainer()->get(WorkOrderRepository::class)->findOneBy(['number' => 'WO-2026-0001']);
        $this->assertNotNull($workOrder);

        $crawler = $client->request('GET', '/work-orders/' . $workOrder->getId() . '/payments/new');
        $this->assertResponseIsSuccessful();

        // The work_order field should be pre-selected and the form should
        // submit cleanly without re-picking it.
        $form = $crawler->filter('form')->form();
        $form['payment[amount]'] = '12.34';
        $form['payment[method]'] = 'cash';

        $client->submit($form);
        $this->assertResponseRedirects();
    }

    public function testDeletePaymentRemovesIt(): void
    {
        $client = $this->loginClient();

        $em = static::getContainer()->get('doctrine')->getManager();
        $workOrder = static::getContainer()->get(WorkOrderRepository::class)->findOneBy(['number' => 'WO-2026-0001']);

        $payment = new Payment();
        $payment->setWorkOrder($workOrder);
        $payment->setAmount('5.00');
        $payment->setMethod(\App\Enum\PaymentMethod::Cash);
        $em->persist($payment);
        $em->flush();
        $paymentId = $payment->getId();
        $em->clear();

        $crawler = $client->request('GET', '/payments/' . $paymentId);
        $form = $crawler->filter('form[action$="/delete"]')->form();
        $client->submit($form);

        $this->assertResponseRedirects('/payments');

        $this->assertNull(static::getContainer()->get(PaymentRepository::class)->find($paymentId),
            'Payment must be removed after delete.');
    }

    public function testEmptyOptionalDateTimeFieldSubmits(): void
    {
        $client = $this->loginClient();

        $workOrderRepo = static::getContainer()->get(WorkOrderRepository::class);
        $workOrder = $workOrderRepo->findOneBy(['number' => 'WO-2026-0001']);
        $this->assertNotNull($workOrder);

        $crawler = $client->request('GET', '/work-orders/' . $workOrder->getId() . '/edit');
        $this->assertResponseIsSuccessful();

        $form = $crawler->filter('form[name="work_order"]')->form();
        $form['work_order[scheduledAt]'] = '';

        $client->submit($form);
        $this->assertResponseRedirects('/work-orders/' . $workOrder->getId());

        static::getContainer()->get('doctrine')->getManager()->clear();
        $workOrder = $workOrderRepo->findOneBy(['number' => 'WO-2026-0001']);
        $this->assertNull($workOrder->getScheduledAt());
    }
}
