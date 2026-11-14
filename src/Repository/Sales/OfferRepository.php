<?php

namespace App\Repository\Sales;

use App\Entity\Client\Client;
use App\Entity\Sales\Offer;
use App\Enum\OfferStatus;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Offer>
 */
class OfferRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Offer::class);
    }

    /**
     * @return Offer[]
     */
    public function findForListing(string $search, ?string $status): array
    {
        $qb = $this->createQueryBuilder('o')
            ->join('o.client', 'c')
            ->join('o.car', 'car')
            ->orderBy('o.id', 'DESC');

        if ($search !== '') {
            $qb->andWhere('LOWER(o.number) LIKE :q OR LOWER(c.name) LIKE :q')
                ->setParameter('q', '%' . mb_strtolower($search) . '%');
        }

        if ($status !== null && $status !== '' && OfferStatus::tryFrom($status) !== null) {
            $qb->andWhere('o.status = :status')->setParameter('status', $status);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Most recent offers for one client. Used by the client profile page
     * so the user can scan and jump straight to a related offer without
     * leaving the page.
     *
     * @return list<Offer>
     */
    public function findRecentForClient(Client $client, int $limit = 10): array
    {
        return $this->createQueryBuilder('o')
            ->andWhere('o.client = :client')
            ->setParameter('client', $client)
            ->orderBy('o.createdAt', 'DESC')
            ->addOrderBy('o.id', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * @return array<string, int> map of status value => count for the given period
     */
    public function countByStatusForPeriod(\DateTimeInterface $start, \DateTimeInterface $end): array
    {
        $rows = $this->createQueryBuilder('o')
            ->select('o.status AS status, COUNT(o.id) AS total')
            ->andWhere('o.createdAt >= :start')
            ->andWhere('o.createdAt < :end')
            ->setParameter('start', $start)
            ->setParameter('end', $end)
            ->groupBy('o.status')
            ->getQuery()
            ->getArrayResult();

        $result = [];
        foreach ($rows as $row) {
            $status = $row['status'] instanceof OfferStatus ? $row['status']->value : (string) $row['status'];
            $result[$status] = (int) $row['total'];
        }

        return $result;
    }

    /**
     * Numerator (converted offers) and denominator (all non-draft offers)
     * for the conversion-rate KPI. Returned separately so the controller
     * can decide how to display the "no data yet" case rather than
     * silently dividing.
     *
     * @return array{converted: int, nonDraft: int}
     */
    public function offerConversionForPeriod(\DateTimeInterface $start, \DateTimeInterface $end): array
    {
        $counts = $this->countByStatusForPeriod($start, $end);

        $converted = $counts[OfferStatus::Converted->value] ?? 0;

        $nonDraft = 0;
        foreach ($counts as $status => $count) {
            if ($status === OfferStatus::Draft->value) {
                continue;
            }
            $nonDraft += $count;
        }

        return ['converted' => $converted, 'nonDraft' => $nonDraft];
    }
}
