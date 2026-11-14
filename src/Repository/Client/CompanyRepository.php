<?php

namespace App\Repository\Client;

use App\Entity\Client\Company;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Company>
 */
class CompanyRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Company::class);
    }

    /**
     * @return Company[]
     */
    public function searchByQuery(string $query, int $limit): array
    {
        return $this->createQueryBuilder('co')
            ->where('LOWER(co.name) LIKE :q')
            ->orWhere('LOWER(co.identifier) LIKE :q')
            ->orWhere('LOWER(co.vatIdentifier) LIKE :q')
            ->setParameter('q', '%' . mb_strtolower($query) . '%')
            ->orderBy('co.name', 'ASC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }
}
