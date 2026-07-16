<?php

declare(strict_types=1);

namespace App\Service\Sales;

use App\Entity\Sales\Offer;
use App\Enum\OfferStatus;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Applies a manual status change to an offer.
 *
 * The "Converted" status is owned by {@see \App\Service\OfferToWorkOrderConverter}
 * and must never be set through this updater - once an offer is converted,
 * it becomes read-only from the UI's perspective.
 */
final class OfferStatusUpdater
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function isLocked(Offer $offer): bool
    {
        return $offer->getStatus() === OfferStatus::Converted;
    }

    /**
     * @throws \DomainException if the new status is invalid for manual update
     *                          or if the offer is already locked.
     */
    public function update(Offer $offer, OfferStatus $newStatus): void
    {
        if ($newStatus === OfferStatus::Converted) {
            throw new \DomainException('Converted status is set by the conversion workflow only.');
        }

        if ($this->isLocked($offer)) {
            throw new \DomainException('Offer is locked because it was already converted.');
        }

        $offer->setStatus($newStatus);
        $offer->setUpdatedAt(new \DateTimeImmutable());

        $this->entityManager->flush();
    }
}
