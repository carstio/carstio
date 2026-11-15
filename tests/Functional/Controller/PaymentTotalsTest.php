<?php

declare(strict_types=1);

namespace App\Tests\Functional\Controller;

use App\Entity\Workshop\Payment;
use App\Entity\Workshop\WorkOrder;
use App\Enum\PaymentMethod;
use App\Enum\PaymentStatus;
use App\Repository\UserRepository;
use App\Repository\Workshop\PaymentRepository;
use App\Service\Payment\PaymentManager;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Adding or deleting a payment must change what is STORED on the work
 * order (paid amount, payment status): lists, statistics and "unpaid"
 * filters read those columns, they do not add up payments themselves.
 */
final class PaymentTotalsTest extends WebTestCase
{
    private KernelBrowser $client;
    private int $workOrderId;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->client->loginUser(static::getContainer()->get(UserRepository::class)->findOneBy(['email' => 'admin@carstio.local']));

        // A work order worth 120.00 with nothing paid yet.
        foreach ($this->entityManager()->getRepository(WorkOrder::class)->findAll() as $workOrder) {
            if ($workOrder->getTotalAmount() === '120.00' && $workOrder->getPayments()->isEmpty()) {
                $this->workOrderId = (int) $workOrder->getId();

                return;
            }
        }

        self::fail('The fixtures should contain an unpaid work order with a total of 120.00.');
    }

    public function testAFullPaymentMarksTheWorkOrderPaid(): void
    {
        $this->addPayment('120.00');

        self::assertSame(['paid_amount' => '120.00', 'payment_status' => 'paid'], $this->stored());
    }

    public function testPartialPaymentsAddUp(): void
    {
        $this->addPayment('50.00');
        self::assertSame(['paid_amount' => '50.00', 'payment_status' => 'partially_paid'], $this->stored());

        $this->addPayment('30.00');
        self::assertSame(['paid_amount' => '80.00', 'payment_status' => 'partially_paid'], $this->stored());

        $this->addPayment('40.00');
        self::assertSame(['paid_amount' => '120.00', 'payment_status' => 'paid'], $this->stored());
    }

    public function testAPaymentThatIsNotMarkedPaidIsNotCounted(): void
    {
        $this->addPayment('120.00', PaymentStatus::Pending);

        self::assertSame(['paid_amount' => '0.00', 'payment_status' => 'pending'], $this->stored());
    }

    public function testAPaymentEnteredFromThePaymentsListIsCountedToo(): void
    {
        $crawler = $this->client->request('GET', '/payments/new');
        $form = $crawler->filter('form[name="payment"]')->form();
        $form['payment[workOrder]'] = (string) $this->workOrderId;
        $form['payment[amount]'] = '120.00';
        $form['payment[method]'] = PaymentMethod::BankTransfer->value;
        $form['payment[status]'] = PaymentStatus::Paid->value;
        $this->client->submit($form);
        self::assertResponseRedirects();

        self::assertSame(['paid_amount' => '120.00', 'payment_status' => 'paid'], $this->stored());
    }

    public function testDeletingAPaymentTakesItOffTheWorkOrderAgain(): void
    {
        $this->addPayment('50.00');
        $this->addPayment('70.00');
        self::assertSame(['paid_amount' => '120.00', 'payment_status' => 'paid'], $this->stored());

        $payments = static::getContainer()->get(PaymentRepository::class)->findBy(['workOrder' => $this->workOrderId], ['amount' => 'ASC']);
        self::assertCount(2, $payments);

        $crawler = $this->client->request('GET', '/payments/' . $payments[1]->getId());
        $this->client->submit($crawler->filter('form[action$="/delete"]')->form());
        self::assertResponseRedirects('/payments');

        self::assertSame(['paid_amount' => '50.00', 'payment_status' => 'partially_paid'], $this->stored());
        self::assertCount(1, static::getContainer()->get(PaymentRepository::class)->findBy(['workOrder' => $this->workOrderId]));
    }

    public function testTotalsAreRightEvenWhenTheWorkOrdersPaymentsWereAlreadyLoaded(): void
    {
        $entityManager = $this->entityManager();
        $manager = static::getContainer()->get(PaymentManager::class);
        $workOrder = $entityManager->find(WorkOrder::class, $this->workOrderId);
        self::assertNotNull($workOrder);

        // Looked at before anything changes: the collection is now in memory.
        self::assertCount(0, $workOrder->getPayments());

        $first = $this->payment($workOrder, '100.00');
        $manager->record($first);
        $manager->record($this->payment($workOrder, '20.00'));
        self::assertSame(['paid_amount' => '120.00', 'payment_status' => 'paid'], $this->stored());

        self::assertTrue($manager->delete($first));
        self::assertSame(['paid_amount' => '20.00', 'payment_status' => 'partially_paid'], $this->stored());
        self::assertCount(1, $workOrder->getPayments());

        $entityManager->clear();
        self::assertNull($entityManager->find(Payment::class, $first->getId() ?? 0), 'The deleted payment is gone from the database.');
    }

    public function testTheWorkOrderPageAndTheStoredValuesAgree(): void
    {
        $this->addPayment('50.00');

        $this->client->request('GET', '/work-orders/' . $this->workOrderId);
        self::assertResponseIsSuccessful();
        self::assertSame(['paid_amount' => '50.00', 'payment_status' => 'partially_paid'], $this->stored());
    }

    private function addPayment(string $amount, PaymentStatus $status = PaymentStatus::Paid): void
    {
        $crawler = $this->client->request('GET', '/work-orders/' . $this->workOrderId . '/payments/new');
        self::assertResponseIsSuccessful();

        $form = $crawler->filter('form[name="payment"]')->form();
        $form['payment[amount]'] = $amount;
        $form['payment[method]'] = PaymentMethod::Cash->value;
        $form['payment[status]'] = $status->value;
        $this->client->submit($form);
        self::assertResponseRedirects();
    }

    private function payment(WorkOrder $workOrder, string $amount): Payment
    {
        return (new Payment())
            ->setWorkOrder($workOrder)
            ->setAmount($amount)
            ->setMethod(PaymentMethod::Cash)
            ->setStatus(PaymentStatus::Paid)
            ->setPaidAt(new \DateTimeImmutable());
    }

    /**
     * What the database holds, bypassing anything still in memory.
     *
     * @return array{paid_amount: string, payment_status: string}
     */
    private function stored(): array
    {
        $row = $this->entityManager()->getConnection()->fetchAssociative(
            'SELECT paid_amount, payment_status FROM work_order WHERE id = ?',
            [$this->workOrderId],
        );
        self::assertIsArray($row);

        return ['paid_amount' => (string) $row['paid_amount'], 'payment_status' => (string) $row['payment_status']];
    }

    private function entityManager(): EntityManagerInterface
    {
        return static::getContainer()->get(EntityManagerInterface::class);
    }
}
