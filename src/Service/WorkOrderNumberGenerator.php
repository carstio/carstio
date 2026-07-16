<?php

namespace App\Service;

use App\Repository\Workshop\WorkOrderRepository;
use App\Service\Settings\SettingsProvider;
use App\Settings\SettingsRegistry;

class WorkOrderNumberGenerator
{
    public function __construct(
        private readonly WorkOrderRepository $repository,
        private readonly SettingsProvider $settings,
    ) {
    }

    public function generate(): string
    {
        $year = date('Y');
        $configured = $this->settings->getString(
            SettingsRegistry::SECTION_DOCUMENTS,
            SettingsRegistry::KEY_WORK_ORDER_NUMBER_PREFIX,
        );
        $base = $configured !== null && $configured !== '' ? $configured : 'WO';
        $prefix = $base . '-' . $year . '-';

        $lastOrder = $this->repository->createQueryBuilder('w')
            ->where('w.number LIKE :prefix')
            ->setParameter('prefix', $prefix . '%')
            ->orderBy('w.number', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        if ($lastOrder) {
            $lastNumber = (int) substr($lastOrder->getNumber(), strlen($prefix));
            $next = $lastNumber + 1;
        } else {
            $next = 1;
        }

        return $prefix . str_pad((string) $next, 4, '0', STR_PAD_LEFT);
    }
}
