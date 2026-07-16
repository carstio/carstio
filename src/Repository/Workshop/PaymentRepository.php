<?php

namespace App\Repository\Workshop;

use App\Entity\Workshop\Payment;
use App\Enum\PaymentMethod;
use App\Enum\PaymentStatus;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Payment>
 */
class PaymentRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Payment::class);
    }

    /**
     * @return Payment[]
     */
    public function findAllOrdered(): array
    {
        return $this->createQueryBuilder('p')
            ->join('p.workOrder', 'w')->addSelect('w')
            ->orderBy('p.id', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Sum of paid-status payments grouped by payment method for the period,
     * restricted to one currency via the parent work order.
     *
     * @return array<string, string> map of method value => 2-decimal sum
     */
    public function sumByMethodForPeriod(\DateTimeInterface $start, \DateTimeInterface $end, string $currencyCode): array
    {
        $rows = $this->createQueryBuilder('p')
            ->select('p.method AS method', 'COALESCE(SUM(p.amount), 0) AS total')
            ->join('p.workOrder', 'w')
            ->andWhere('p.paidAt >= :start')
            ->andWhere('p.paidAt < :end')
            ->andWhere('p.status = :paid')
            ->andWhere('w.currencyCode = :currency')
            ->setParameter('start', $start)
            ->setParameter('end', $end)
            ->setParameter('paid', PaymentStatus::Paid)
            ->setParameter('currency', $currencyCode)
            ->groupBy('p.method')
            ->getQuery()
            ->getArrayResult();

        $result = [];
        foreach ($rows as $row) {
            $method = $row['method'] instanceof PaymentMethod ? $row['method']->value : (string) $row['method'];
            $result[$method] = number_format((float) $row['total'], 2, '.', '');
        }

        return $result;
    }
}
