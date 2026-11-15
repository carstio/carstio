<?php

declare(strict_types=1);

namespace App\Service\User;

use App\Entity\User;
use App\Enum\UserRole;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Owns all write-side business rules for staff accounts:
 *
 * - hashing the plain password before persisting
 * - enforcing one role per user (ROLE_ADMIN xor ROLE_MECHANIC)
 * - guarding against actions that would leave zero active admins
 *
 * Controllers stay thin: they validate the form, then call into here.
 */
final class UserAccountManager
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly UserRepository $userRepository,
        private readonly UserPasswordHasherInterface $passwordHasher,
    ) {
    }

    public function create(User $user, string $plainPassword, UserRole $role): void
    {
        $user->setRoles([$role->value]);
        $user->setPassword($this->passwordHasher->hashPassword($user, $plainPassword));
        $user->setIsActive(true);

        $this->entityManager->persist($user);
        $this->entityManager->flush();
    }

    /**
     * Updates email/full-name (already mapped on the entity), the role, and
     * optionally the password. An empty $plainPassword keeps the current hash.
     *
     * @throws LastAdminException if the change would demote the last active admin
     */
    public function update(User $user, ?string $plainPassword, UserRole $role, bool $isActive): void
    {
        $wouldStillHaveAdmin = $role === UserRole::Admin && $isActive;
        if (!$wouldStillHaveAdmin && $this->isLastActiveAdmin($user)) {
            throw new LastAdminException('user.error.last_admin');
        }

        $user->setRoles([$role->value]);
        $user->setIsActive($isActive);

        if ($plainPassword !== null && $plainPassword !== '') {
            $user->setPassword($this->passwordHasher->hashPassword($user, $plainPassword));
        }

        $this->entityManager->flush();
    }

    /**
     * Replaces the password and nothing else. Every session that is open
     * for the account ends with it (see User::isEqualTo()).
     */
    public function changePassword(User $user, string $plainPassword): void
    {
        $user->setPassword($this->passwordHasher->hashPassword($user, $plainPassword));
        $this->entityManager->flush();
    }

    /**
     * @throws LastAdminException
     */
    public function deactivate(User $user): void
    {
        if ($this->isLastActiveAdmin($user)) {
            throw new LastAdminException('user.error.last_admin');
        }

        $user->setIsActive(false);
        $this->entityManager->flush();
    }

    public function activate(User $user): void
    {
        $user->setIsActive(true);
        $this->entityManager->flush();
    }

    /**
     * True when $user is the only admin left who is currently active.
     * Used both as a precondition for deactivation and as a guard against
     * role demotion ("change Admin → Mechanic on the last admin").
     */
    private function isLastActiveAdmin(User $user): bool
    {
        if (!$user->isActive()) {
            return false;
        }
        if (!in_array(UserRole::Admin->value, $user->getRoles(), true)) {
            return false;
        }

        return $this->userRepository->countActiveAdmins(excluded: $user) === 0;
    }
}
