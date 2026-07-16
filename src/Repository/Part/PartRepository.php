<?php

namespace App\Repository\Part;

use App\Entity\Part\Part;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Part>
 */
class PartRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Part::class);
    }

    /**
     * @return Part[]
     */
    public function findForListing(string $search): array
    {
        if ($search === '') {
            return $this->findBy([], ['name' => 'ASC']);
        }

        return $this->createQueryBuilder('p')
            ->where('LOWER(p.name) LIKE :q')
            ->orWhere('LOWER(p.sku) LIKE :q')
            ->orWhere('LOWER(p.partNumber) LIKE :q')
            ->orWhere('LOWER(p.brand) LIKE :q')
            ->setParameter('q', '%' . strtolower($search) . '%')
            ->orderBy('p.name', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
