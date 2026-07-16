<?php

declare(strict_types=1);

namespace App\Service;

use Doctrine\DBAL\Exception\ForeignKeyConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

/**
 * Removes an entity and converts foreign-key violations into a soft
 * failure so controllers can flash a user-friendly message instead of
 * bubbling a raw database exception up to the user.
 */
final class EntityDeleter
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function delete(object $entity): bool
    {
        try {
            $this->em->remove($entity);
            $this->em->flush();

            return true;
        } catch (ForeignKeyConstraintViolationException $e) {
            $this->logger->warning('Delete blocked by foreign key constraint.', [
                'entity' => $entity::class,
                'exception' => $e->getMessage(),
            ]);

            return false;
        }
    }
}
