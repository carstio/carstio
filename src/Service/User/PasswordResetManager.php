<?php

declare(strict_types=1);

namespace App\Service\User;

use App\Entity\User;
use App\Repository\UserRepository;
use App\Security\PasswordReset\PasswordResetLinks;
use App\Service\Email\PasswordResetMailer;

/**
 * The "forgot my password" workflow: someone asks for a link by email
 * address, the account's owner receives it, and uses it to choose a new
 * password.
 */
final class PasswordResetManager
{
    public function __construct(
        private readonly UserRepository $users,
        private readonly PasswordResetLinks $links,
        private readonly PasswordResetMailer $mailer,
        private readonly UserAccountManager $accounts,
    ) {
    }

    public function isAvailable(): bool
    {
        return $this->mailer->isAvailable();
    }

    /**
     * Sends a link to the address if it belongs to an active account, and
     * does nothing otherwise. Deliberately returns nothing: the caller
     * answers the same way in both cases.
     */
    public function request(string $email): void
    {
        $user = $this->users->loadUserByIdentifier($email);
        if (!$user instanceof User || !$user->isActive()) {
            return;
        }

        $this->mailer->send($user, $this->links->createUrl($user));
    }

    /**
     * The account a link from the email is for; null when the link cannot
     * be used (any more).
     */
    public function userForLink(int $userId, int $expires, string $signature): ?User
    {
        return $this->links->resolve($userId, $expires, $signature);
    }

    /**
     * Changing the password also uses up the link and ends every session
     * that is still open for the account.
     */
    public function setNewPassword(User $user, string $plainPassword): void
    {
        $this->accounts->changePassword($user, $plainPassword);
    }
}
