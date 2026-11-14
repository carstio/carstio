<?php

namespace App\Repository\Client;

use App\Entity\Client\Client;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Client>
 */
class ClientRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Client::class);
    }

    /**
     * @return Client[]
     */
    public function findForListing(string $search): array
    {
        if ($search === '') {
            return $this->findBy([], ['name' => 'ASC']);
        }

        return $this->createQueryBuilder('c')
            ->where('LOWER(c.name) LIKE :q')
            ->orWhere('LOWER(c.phone) LIKE :q')
            ->orWhere('LOWER(c.email) LIKE :q')
            ->setParameter('q', '%' . mb_strtolower($search) . '%')
            ->orderBy('c.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return Client[]
     */
    public function searchByQuery(string $query, int $limit): array
    {
        return $this->createQueryBuilder('c')
            ->where('LOWER(c.name) LIKE :q')
            ->orWhere('LOWER(c.phone) LIKE :q')
            ->orWhere('LOWER(c.email) LIKE :q')
            ->setParameter('q', '%' . mb_strtolower($query) . '%')
            ->orderBy('c.name', 'ASC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }
}
