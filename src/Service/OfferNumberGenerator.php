<?php

namespace App\Service;

use App\Repository\Sales\OfferRepository;
use App\Service\Settings\SettingsProvider;
use App\Settings\SettingsRegistry;

class OfferNumberGenerator
{
    public function __construct(
        private readonly OfferRepository $repository,
        private readonly SettingsProvider $settings,
    ) {
    }

    public function generate(): string
    {
        $year = date('Y');
        $configured = $this->settings->getString(
            SettingsRegistry::SECTION_DOCUMENTS,
            SettingsRegistry::KEY_OFFER_NUMBER_PREFIX,
        );
        $base = $configured !== null && $configured !== '' ? $configured : 'OF';
        $prefix = $base . '-' . $year . '-';

        $lastOffer = $this->repository->createQueryBuilder('o')
            ->where('o.number LIKE :prefix')
            ->setParameter('prefix', $prefix . '%')
            // Longest number first, then lexicographic: "…-10000" must beat
            // "…-9999" even though it sorts lower as a plain string.
            ->orderBy('LENGTH(o.number)', 'DESC')
            ->addOrderBy('o.number', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        if ($lastOffer) {
            $lastNumber = (int) substr($lastOffer->getNumber(), strlen($prefix));
            $next = $lastNumber + 1;
        } else {
            $next = 1;
        }

        return $prefix . str_pad((string) $next, 4, '0', STR_PAD_LEFT);
    }
}
