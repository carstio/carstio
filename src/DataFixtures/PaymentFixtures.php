<?php

namespace App\DataFixtures;

use App\Entity\Workshop\Payment;
use App\Entity\Workshop\WorkOrder;
use App\Enum\PaymentMethod;
use App\Enum\PaymentStatus;
use App\Service\WorkOrderCalculator;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;

class PaymentFixtures extends Fixture implements DependentFixtureInterface
{
    public function __construct(
        private readonly WorkOrderCalculator $calculator,
    ) {
    }

    public function load(ObjectManager $manager): void
    {
        // Full payment for Alice's completed work order
        $wo2 = $this->getReference(WorkOrderFixtures::WO_ALICE_X5, WorkOrder::class);

        $payment = new Payment();
        $wo2->addPayment($payment);
        $payment->setAmount($wo2->getTotalAmount());
        $payment->setMethod(PaymentMethod::Card);
        $payment->setStatus(PaymentStatus::Paid);
        $payment->setPaidAt(new \DateTimeImmutable('-2 days'));
        $payment->setReference('CARD-20260410-001');
        $manager->persist($payment);

        // Partial cash deposit on John's in-progress work order so the
        // statistics dashboard can render multiple payment methods.
        $wo1 = $this->getReference(WorkOrderFixtures::WO_JOHN_COROLLA, WorkOrder::class);
        $deposit = new Payment();
        $wo1->addPayment($deposit);
        $deposit->setAmount('50.00');
        $deposit->setMethod(PaymentMethod::Cash);
        $deposit->setStatus(PaymentStatus::Paid);
        $deposit->setPaidAt(new \DateTimeImmutable('-1 day'));
        $deposit->setReference('CASH-20260411-001');
        $manager->persist($deposit);

        $manager->flush();

        $this->calculator->recalculate($wo1);
        $this->calculator->recalculate($wo2);
        $manager->flush();
    }

    public function getDependencies(): array
    {
        return [
            WorkOrderFixtures::class,
        ];
    }
}
