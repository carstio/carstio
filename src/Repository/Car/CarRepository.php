<?php

namespace App\Repository\Car;

use App\Entity\Car\Car;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Car>
 */
class CarRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Car::class);
    }

    /**
     * @return Car[]
     */
    public function findForListing(string $search): array
    {
        $qb = $this->createQueryBuilder('c')
            ->join('c.client', 'cl')->addSelect('cl')
            ->join('c.model', 'm')->addSelect('m')
            ->join('m.brand', 'b')->addSelect('b')
            ->orderBy('c.id', 'DESC');

        if ($search === '') {
            return $qb->getQuery()->getResult();
        }

        return $qb
            ->andWhere(
                'LOWER(cl.name) LIKE :q'
                . ' OR LOWER(m.name) LIKE :q'
                . ' OR LOWER(b.name) LIKE :q'
                . ' OR LOWER(c.vin) LIKE :q'
                . ' OR LOWER(c.licensePlate) LIKE :q'
            )
            ->setParameter('q', '%' . strtolower($search) . '%')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return Car[]
     */
    public function searchByQuery(string $query, int $limit): array
    {
        return $this->createQueryBuilder('c')
            ->join('c.client', 'cl')->addSelect('cl')
            ->join('c.model', 'm')->addSelect('m')
            ->join('m.brand', 'b')->addSelect('b')
            ->where('LOWER(cl.name) LIKE :q')
            ->orWhere('LOWER(m.name) LIKE :q')
            ->orWhere('LOWER(b.name) LIKE :q')
            ->orWhere('LOWER(c.vin) LIKE :q')
            ->orWhere('LOWER(c.licensePlate) LIKE :q')
            ->setParameter('q', '%' . strtolower($query) . '%')
            ->orderBy('c.id', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }
}
