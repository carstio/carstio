<?php

declare(strict_types=1);

namespace App\Service\Payment;

use App\Entity\Workshop\Payment;
use App\Service\WorkOrderCalculator;
use Doctrine\DBAL\Exception\ForeignKeyConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

/**
 * Records and removes payments, keeping the work order's stored paid
 * amount and payment status in step.
 *
 * A work order adds up the payments in its own collection. A payment that
 * merely points at the work order is not in that collection until it is
 * put there, and one that was deleted stays in it until it is taken out -
 * so both happen here, before the totals are recalculated, and everything
 * is saved together.
 */
final class PaymentManager
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly WorkOrderCalculator $calculator,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function record(Payment $payment): void
    {
        $workOrder = $payment->getWorkOrder() ?? throw new \LogicException('A payment belongs to a work order.');

        $workOrder->addPayment($payment);
        $this->entityManager->persist($payment);
        $this->calculator->recalculate($workOrder);
        $this->entityManager->flush();
    }

    /**
     * @return bool false when the database refused the deletion because
     *              something still refers to the payment
     */
    public function delete(Payment $payment): bool
    {
        $workOrder = $payment->getWorkOrder();

        if ($workOrder === null) {
            $this->entityManager->remove($payment);
        } else {
            // Taking it out of the collection is what deletes it (the
            // collection removes orphans), and what makes the totals right.
            $workOrder->getPayments()->removeElement($payment);
            $this->calculator->recalculate($workOrder);
        }

        try {
            $this->entityManager->flush();
        } catch (ForeignKeyConstraintViolationException $e) {
            $this->logger->warning('Delete blocked by foreign key constraint.', [
                'entity' => Payment::class,
                'exception' => $e->getMessage(),
            ]);

            return false;
        }

        return true;
    }
}
