<?php

namespace App\Repository;

use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Bridge\Doctrine\Security\User\UserLoaderInterface;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\PasswordUpgraderInterface;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * @extends ServiceEntityRepository<User>
 */
class UserRepository extends ServiceEntityRepository implements PasswordUpgraderInterface, UserLoaderInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, User::class);
    }

    /**
     * Login lookup. Emails are stored lower-cased ({@see User::setEmail()}),
     * so normalising the submitted identifier makes the login
     * case-insensitive on PostgreSQL as well as on MariaDB (whose default
     * collation already ignores case).
     */
    public function loadUserByIdentifier(string $identifier): ?UserInterface
    {
        return $this->findOneBy(['email' => User::normalizeEmail($identifier)]);
    }

    public function upgradePassword(PasswordAuthenticatedUserInterface $user, string $newHashedPassword): void
    {
        if (!$user instanceof User) {
            throw new UnsupportedUserException(sprintf('Instances of "%s" are not supported.', $user::class));
        }

        $user->setPassword($newHashedPassword);
        $this->getEntityManager()->persist($user);
        $this->getEntityManager()->flush();
    }

    /**
     * @return list<User>
     */
    public function findAllOrderedByName(): array
    {
        return $this->createQueryBuilder('u')
            ->orderBy('u.fullName', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Counts users that are currently active AND hold ROLE_ADMIN. Roles are
     * stored as a JSON array on the column, which is portable but awkward to
     * filter in DQL - counting in PHP is fast enough for staff accounts.
     */
    public function countActiveAdmins(?User $excluded = null): int
    {
        $excludedId = $excluded?->getId();
        $count = 0;

        foreach ($this->createQueryBuilder('u')
            ->where('u.isActive = :active')
            ->setParameter('active', true)
            ->getQuery()
            ->toIterable() as $user
        ) {
            /** @var User $user */
            if ($excludedId !== null && $user->getId() === $excludedId) {
                continue;
            }
            if (in_array('ROLE_ADMIN', $user->getRoles(), true)) {
                ++$count;
            }
        }

        return $count;
    }
}
