<?php

namespace App\Repository\Workshop;

use App\Entity\Client\Client;
use App\Entity\Workshop\WorkOrder;
use App\Enum\WorkOrderStatus;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<WorkOrder>
 */
class WorkOrderRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, WorkOrder::class);
    }

    /**
     * @return WorkOrder[]
     */
    public function findForListing(string $search, ?string $status): array
    {
        $qb = $this->createQueryBuilder('w')
            ->join('w.client', 'c')
            ->join('w.car', 'car')
            ->orderBy('w.id', 'DESC');

        if ($search !== '') {
            $qb->andWhere('LOWER(w.number) LIKE :q OR LOWER(c.name) LIKE :q')
                ->setParameter('q', '%' . strtolower($search) . '%');
        }

        if ($status !== null && $status !== '' && WorkOrderStatus::tryFrom($status) !== null) {
            $qb->andWhere('w.status = :status')->setParameter('status', $status);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Most recent work orders for one client. Used by the client profile
     * page so the user can scan and jump straight to a related WO without
     * leaving the page.
     *
     * @return list<WorkOrder>
     */
    public function findRecentForClient(Client $client, int $limit = 10): array
    {
        return $this->createQueryBuilder('w')
            ->andWhere('w.client = :client')
            ->setParameter('client', $client)
            ->orderBy('w.openedAt', 'DESC')
            ->addOrderBy('w.id', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Service history for one car, newest first. Used by the car profile
     * page so the user can see when this car was last in and what the
     * recorded mileage was.
     *
     * @return list<WorkOrder>
     */
    public function findHistoryForCar(\App\Entity\Car\Car $car, int $limit = 20): array
    {
        return $this->createQueryBuilder('w')
            ->andWhere('w.car = :car')
            ->setParameter('car', $car)
            ->orderBy('w.openedAt', 'DESC')
            ->addOrderBy('w.id', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Latest WorkOrder for a car that actually has an odometer reading.
     * Returns null if no recorded reading exists yet. Ordered by openedAt
     * (the service event date) rather than the numerically largest reading,
     * because a corrected/replaced odometer can legitimately produce a
     * lower number later.
     */
    public function findLatestWithMileageForCar(\App\Entity\Car\Car $car): ?WorkOrder
    {
        return $this->createQueryBuilder('w')
            ->andWhere('w.car = :car')
            ->andWhere('w.odometerReading IS NOT NULL')
            ->setParameter('car', $car)
            ->orderBy('w.openedAt', 'DESC')
            ->addOrderBy('w.id', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function countOpen(): int
    {
        return $this->createQueryBuilder('w')
            ->select('COUNT(w.id)')
            ->where('w.status IN (:statuses)')
            ->setParameter('statuses', [
                WorkOrderStatus::Draft,
                WorkOrderStatus::Open,
                WorkOrderStatus::InProgress,
                WorkOrderStatus::WaitingParts,
                WorkOrderStatus::WaitingApproval,
            ])
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * @return WorkOrder[]
     */
    public function findScheduledBetween(\DateTimeInterface $start, \DateTimeInterface $end): array
    {
        return $this->createQueryBuilder('w')
            ->andWhere('w.scheduledAt IS NOT NULL')
            ->andWhere('w.scheduledAt >= :start')
            ->andWhere('w.scheduledAt < :end')
            ->setParameter('start', $start)
            ->setParameter('end', $end)
            ->orderBy('w.scheduledAt', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function countScheduled(): int
    {
        return (int) $this->createQueryBuilder('w')
            ->select('COUNT(w.id)')
            ->andWhere('w.scheduledAt IS NOT NULL')
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function countCompletedToday(): int
    {
        $today = new \DateTimeImmutable('today');
        $tomorrow = $today->modify('+1 day');

        return $this->createQueryBuilder('w')
            ->select('COUNT(w.id)')
            ->where('w.status = :status')
            ->andWhere('w.completedAt >= :start')
            ->andWhere('w.completedAt < :end')
            ->setParameter('status', WorkOrderStatus::Completed)
            ->setParameter('start', $today)
            ->setParameter('end', $tomorrow)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Sum of work order totals opened within [$start, $end) for one currency.
     * Returned as a 2-decimal string to match the entity's stored type.
     */
    public function sumTotalsForPeriod(\DateTimeInterface $start, \DateTimeInterface $end, string $currencyCode): string
    {
        $sum = $this->createQueryBuilder('w')
            ->select('COALESCE(SUM(w.totalAmount), 0)')
            ->andWhere('w.openedAt >= :start')
            ->andWhere('w.openedAt < :end')
            ->andWhere('w.currencyCode = :currency')
            ->andWhere('w.status != :cancelled')
            ->setParameter('start', $start)
            ->setParameter('end', $end)
            ->setParameter('currency', $currencyCode)
            ->setParameter('cancelled', WorkOrderStatus::Cancelled)
            ->getQuery()
            ->getSingleScalarResult();

        return number_format((float) $sum, 2, '.', '');
    }

    /**
     * Sum of paidAmount on work orders opened within [$start, $end) for one currency.
     */
    public function sumPaidForPeriod(\DateTimeInterface $start, \DateTimeInterface $end, string $currencyCode): string
    {
        $sum = $this->createQueryBuilder('w')
            ->select('COALESCE(SUM(w.paidAmount), 0)')
            ->andWhere('w.openedAt >= :start')
            ->andWhere('w.openedAt < :end')
            ->andWhere('w.currencyCode = :currency')
            ->andWhere('w.status != :cancelled')
            ->setParameter('start', $start)
            ->setParameter('end', $end)
            ->setParameter('currency', $currencyCode)
            ->setParameter('cancelled', WorkOrderStatus::Cancelled)
            ->getQuery()
            ->getSingleScalarResult();

        return number_format((float) $sum, 2, '.', '');
    }

    /**
     * @return array<string, int> map of status value => count for the given period
     */
    public function countByStatusForPeriod(\DateTimeInterface $start, \DateTimeInterface $end): array
    {
        $rows = $this->createQueryBuilder('w')
            ->select('w.status AS status, COUNT(w.id) AS total')
            ->andWhere('w.openedAt >= :start')
            ->andWhere('w.openedAt < :end')
            ->setParameter('start', $start)
            ->setParameter('end', $end)
            ->groupBy('w.status')
            ->getQuery()
            ->getArrayResult();

        $result = [];
        foreach ($rows as $row) {
            $status = $row['status'] instanceof WorkOrderStatus ? $row['status']->value : (string) $row['status'];
            $result[$status] = (int) $row['total'];
        }

        return $result;
    }

    /**
     * Count of work orders with status Completed AND completedAt within the period.
     * Uses completedAt rather than openedAt so the metric reflects what was
     * actually finished in the window - a work order opened in March but
     * completed in April should count toward April.
     */
    public function countCompletedForPeriod(\DateTimeInterface $start, \DateTimeInterface $end): int
    {
        return (int) $this->createQueryBuilder('w')
            ->select('COUNT(w.id)')
            ->andWhere('w.status = :status')
            ->andWhere('w.completedAt IS NOT NULL')
            ->andWhere('w.completedAt >= :start')
            ->andWhere('w.completedAt < :end')
            ->setParameter('status', WorkOrderStatus::Completed)
            ->setParameter('start', $start)
            ->setParameter('end', $end)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Returns one row per non-cancelled work order in the period, restricted
     * to a single currency. Grouping into daily/weekly/monthly buckets is
     * done in {@see \App\Service\Statistics\StatisticsService} so the same
     * query supports any granularity the chart may need without porting
     * date functions across database vendors.
     *
     * @return list<array{openedAt: \DateTimeInterface, total: string}>
     */
    public function findRevenueRowsForPeriod(\DateTimeInterface $start, \DateTimeInterface $end, string $currencyCode): array
    {
        $rows = $this->createQueryBuilder('w')
            ->select('w.openedAt AS openedAt', 'w.totalAmount AS total')
            ->andWhere('w.openedAt >= :start')
            ->andWhere('w.openedAt < :end')
            ->andWhere('w.currencyCode = :currency')
            ->andWhere('w.status != :cancelled')
            ->setParameter('start', $start)
            ->setParameter('end', $end)
            ->setParameter('currency', $currencyCode)
            ->setParameter('cancelled', WorkOrderStatus::Cancelled)
            ->getQuery()
            ->getArrayResult();

        return array_map(
            static fn (array $row) => [
                'openedAt' => $row['openedAt'],
                'total' => (string) $row['total'],
            ],
            $rows,
        );
    }

    /**
     * @return list<array{clientId: int, clientName: string, workOrderCount: int, totalRevenue: string, unpaidAmount: string}>
     */
    public function topClientsForPeriod(
        \DateTimeInterface $start,
        \DateTimeInterface $end,
        string $currencyCode,
        int $limit = 5,
    ): array {
        $rows = $this->createQueryBuilder('w')
            ->select(
                'c.id AS clientId',
                'c.name AS clientName',
                'COUNT(w.id) AS workOrderCount',
                'COALESCE(SUM(w.totalAmount), 0) AS totalRevenue',
                'COALESCE(SUM(w.totalAmount - w.paidAmount), 0) AS unpaidAmount',
            )
            ->join('w.client', 'c')
            ->andWhere('w.openedAt >= :start')
            ->andWhere('w.openedAt < :end')
            ->andWhere('w.currencyCode = :currency')
            ->andWhere('w.status != :cancelled')
            ->setParameter('start', $start)
            ->setParameter('end', $end)
            ->setParameter('currency', $currencyCode)
            ->setParameter('cancelled', WorkOrderStatus::Cancelled)
            ->groupBy('c.id', 'c.name')
            ->orderBy('totalRevenue', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getArrayResult();

        return array_map(
            static fn (array $row) => [
                'clientId' => (int) $row['clientId'],
                'clientName' => (string) $row['clientName'],
                'workOrderCount' => (int) $row['workOrderCount'],
                'totalRevenue' => number_format((float) $row['totalRevenue'], 2, '.', ''),
                'unpaidAmount' => number_format((float) $row['unpaidAmount'], 2, '.', ''),
            ],
            $rows,
        );
    }

    /**
     * Workload by createdBy (best available proxy for "assigned mechanic").
     *
     * @return list<array{userId: int, fullName: string, activeWorkOrders: int, completedInPeriod: int, totalAssigned: int}>
     */
    public function mechanicWorkloadForPeriod(\DateTimeInterface $start, \DateTimeInterface $end): array
    {
        $openStatuses = [
            WorkOrderStatus::Draft,
            WorkOrderStatus::Open,
            WorkOrderStatus::InProgress,
            WorkOrderStatus::WaitingParts,
            WorkOrderStatus::WaitingApproval,
        ];

        // Active = any work order they own that is currently open (no period filter).
        $activeRows = $this->createQueryBuilder('w')
            ->select('IDENTITY(w.createdBy) AS userId, COUNT(w.id) AS total')
            ->andWhere('w.createdBy IS NOT NULL')
            ->andWhere('w.status IN (:open)')
            ->setParameter('open', $openStatuses)
            ->groupBy('w.createdBy')
            ->getQuery()
            ->getArrayResult();

        $completedRows = $this->createQueryBuilder('w')
            ->select('IDENTITY(w.createdBy) AS userId, COUNT(w.id) AS total')
            ->andWhere('w.createdBy IS NOT NULL')
            ->andWhere('w.status = :completed')
            ->andWhere('w.completedAt IS NOT NULL')
            ->andWhere('w.completedAt >= :start')
            ->andWhere('w.completedAt < :end')
            ->setParameter('completed', WorkOrderStatus::Completed)
            ->setParameter('start', $start)
            ->setParameter('end', $end)
            ->groupBy('w.createdBy')
            ->getQuery()
            ->getArrayResult();

        $totalRows = $this->createQueryBuilder('w')
            ->select('IDENTITY(w.createdBy) AS userId, COUNT(w.id) AS total, u.fullName AS fullName')
            ->join('w.createdBy', 'u')
            ->andWhere('w.openedAt >= :start')
            ->andWhere('w.openedAt < :end')
            ->setParameter('start', $start)
            ->setParameter('end', $end)
            ->groupBy('w.createdBy', 'u.fullName')
            ->orderBy('total', 'DESC')
            ->getQuery()
            ->getArrayResult();

        $active = [];
        foreach ($activeRows as $row) {
            $active[(int) $row['userId']] = (int) $row['total'];
        }

        $completed = [];
        foreach ($completedRows as $row) {
            $completed[(int) $row['userId']] = (int) $row['total'];
        }

        $result = [];
        foreach ($totalRows as $row) {
            $userId = (int) $row['userId'];
            $result[] = [
                'userId' => $userId,
                'fullName' => (string) $row['fullName'],
                'activeWorkOrders' => $active[$userId] ?? 0,
                'completedInPeriod' => $completed[$userId] ?? 0,
                'totalAssigned' => (int) $row['total'],
            ];
        }

        return $result;
    }

    /**
     * @return list<string> distinct currency codes used by work orders opened in the period
     */
    public function distinctCurrenciesForPeriod(\DateTimeInterface $start, \DateTimeInterface $end): array
    {
        $rows = $this->createQueryBuilder('w')
            ->select('DISTINCT w.currencyCode AS code')
            ->andWhere('w.openedAt >= :start')
            ->andWhere('w.openedAt < :end')
            ->setParameter('start', $start)
            ->setParameter('end', $end)
            ->getQuery()
            ->getArrayResult();

        return array_values(array_map(static fn (array $r) => (string) $r['code'], $rows));
    }
}
