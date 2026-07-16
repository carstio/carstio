<?php

namespace App\Repository\Workshop;

use App\Entity\Workshop\WorkOrderItem;
use App\Enum\WorkOrderItemType;
use App\Enum\WorkOrderStatus;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<WorkOrderItem>
 */
class WorkOrderItemRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, WorkOrderItem::class);
    }

    /**
     * Sum of parts revenue (quantity × unit price) and parts cost
     * (quantity × COALESCE(item.cost_price, part.cost_price)) for all
     * non-cancelled work orders opened in the period and matching the
     * given currency.
     *
     * @return array{revenue: string, cost: string, hasCostData: bool}
     */
    public function sumPartsForPeriod(\DateTimeInterface $start, \DateTimeInterface $end, string $currencyCode): array
    {
        $rows = $this->createQueryBuilder('i')
            ->select(
                'i.quantity AS quantity',
                'i.unitPrice AS unitPrice',
                'i.costPrice AS itemCost',
                'p.costPrice AS catalogCost',
            )
            ->join('i.workOrder', 'w')
            ->leftJoin('i.part', 'p')
            ->andWhere('i.type = :partType')
            ->andWhere('w.openedAt >= :start')
            ->andWhere('w.openedAt < :end')
            ->andWhere('w.currencyCode = :currency')
            ->andWhere('w.status != :cancelled')
            ->setParameter('partType', WorkOrderItemType::Part)
            ->setParameter('start', $start)
            ->setParameter('end', $end)
            ->setParameter('currency', $currencyCode)
            ->setParameter('cancelled', WorkOrderStatus::Cancelled)
            ->getQuery()
            ->getArrayResult();

        $revenue = 0.0;
        $cost = 0.0;
        $hasCost = false;

        foreach ($rows as $row) {
            $quantity = (float) $row['quantity'];
            $revenue += $quantity * (float) $row['unitPrice'];

            $unitCost = $row['itemCost'] !== null && $row['itemCost'] !== ''
                ? (float) $row['itemCost']
                : ($row['catalogCost'] !== null && $row['catalogCost'] !== '' ? (float) $row['catalogCost'] : null);

            if ($unitCost !== null) {
                $cost += $quantity * $unitCost;
                $hasCost = true;
            }
        }

        return [
            'revenue' => number_format($revenue, 2, '.', ''),
            'cost' => number_format($cost, 2, '.', ''),
            'hasCostData' => $hasCost,
        ];
    }

    /**
     * Top parts ranked by computed profit (revenue - cost) over the
     * period. Items without any recorded cost contribute 0 to the cost
     * but are still ranked by their revenue.
     *
     * Lines with the same {@see WorkOrderItem::$name} (case-insensitive)
     * are aggregated together so free-form variants show up alongside
     * catalog parts.
     *
     * @return list<array{name: string, quantity: string, revenue: string, cost: string, profit: string, hasCostData: bool}>
     */
    public function topProfitablePartsForPeriod(
        \DateTimeInterface $start,
        \DateTimeInterface $end,
        string $currencyCode,
        int $limit = 5,
    ): array {
        $rows = $this->createQueryBuilder('i')
            ->select(
                'i.name AS name',
                'i.quantity AS quantity',
                'i.unitPrice AS unitPrice',
                'i.costPrice AS itemCost',
                'p.costPrice AS catalogCost',
            )
            ->join('i.workOrder', 'w')
            ->leftJoin('i.part', 'p')
            ->andWhere('i.type = :partType')
            ->andWhere('w.openedAt >= :start')
            ->andWhere('w.openedAt < :end')
            ->andWhere('w.currencyCode = :currency')
            ->andWhere('w.status != :cancelled')
            ->setParameter('partType', WorkOrderItemType::Part)
            ->setParameter('start', $start)
            ->setParameter('end', $end)
            ->setParameter('currency', $currencyCode)
            ->setParameter('cancelled', WorkOrderStatus::Cancelled)
            ->getQuery()
            ->getArrayResult();

        $aggregated = [];
        foreach ($rows as $row) {
            $name = trim((string) $row['name']);
            if ($name === '') {
                continue;
            }
            $key = mb_strtolower($name);
            $quantity = (float) $row['quantity'];
            $revenue = $quantity * (float) $row['unitPrice'];
            $unitCost = $row['itemCost'] !== null && $row['itemCost'] !== ''
                ? (float) $row['itemCost']
                : ($row['catalogCost'] !== null && $row['catalogCost'] !== '' ? (float) $row['catalogCost'] : null);

            if (!isset($aggregated[$key])) {
                $aggregated[$key] = [
                    'name' => $name,
                    'quantity' => 0.0,
                    'revenue' => 0.0,
                    'cost' => 0.0,
                    'hasCostData' => false,
                ];
            }

            $aggregated[$key]['quantity'] += $quantity;
            $aggregated[$key]['revenue'] += $revenue;
            if ($unitCost !== null) {
                $aggregated[$key]['cost'] += $quantity * $unitCost;
                $aggregated[$key]['hasCostData'] = true;
            }
        }

        $result = array_map(
            static function (array $row): array {
                $profit = $row['revenue'] - $row['cost'];

                return [
                    'name' => $row['name'],
                    'quantity' => number_format($row['quantity'], 2, '.', ''),
                    'revenue' => number_format($row['revenue'], 2, '.', ''),
                    'cost' => number_format($row['cost'], 2, '.', ''),
                    'profit' => number_format($profit, 2, '.', ''),
                    'hasCostData' => $row['hasCostData'],
                ];
            },
            array_values($aggregated),
        );

        usort($result, static fn (array $a, array $b) => (float) $b['profit'] <=> (float) $a['profit']);

        return array_slice($result, 0, $limit);
    }
}
