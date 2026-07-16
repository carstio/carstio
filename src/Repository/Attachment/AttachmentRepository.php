<?php

namespace App\Repository\Attachment;

use App\Entity\Attachment\Attachment;
use App\Enum\AttachmentOwnerType;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Attachment>
 */
class AttachmentRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Attachment::class);
    }

    /**
     * @return Attachment[]
     */
    public function findForOwner(AttachmentOwnerType $ownerType, int $ownerId): array
    {
        return $this->createQueryBuilder('a')
            ->andWhere('a.ownerType = :type')
            ->andWhere('a.ownerId = :id')
            ->setParameter('type', $ownerType)
            ->setParameter('id', $ownerId)
            ->orderBy('a.createdAt', 'DESC')
            ->addOrderBy('a.id', 'DESC')
            ->getQuery()
            ->getResult();
    }
}
