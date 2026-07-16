<?php

namespace App\Repository\Settings;

use App\Entity\Settings\Setting;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Setting>
 */
class SettingRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Setting::class);
    }

    public function findOneByKey(string $section, string $key): ?Setting
    {
        return $this->findOneBy(['section' => $section, 'key' => $key]);
    }

    /**
     * @return list<Setting>
     */
    public function findBySection(string $section): array
    {
        return $this->findBy(['section' => $section], ['sortOrder' => 'ASC']);
    }

    /**
     * Loads every persisted setting in one query. Used by SettingsProvider
     * to warm an in-memory cache so subsequent reads stay cheap.
     *
     * @return list<Setting>
     */
    public function findAllSettings(): array
    {
        /** @var list<Setting> */
        return $this->createQueryBuilder('s')
            ->orderBy('s.section', 'ASC')
            ->addOrderBy('s.sortOrder', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
