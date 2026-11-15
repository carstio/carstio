<?php

declare(strict_types=1);

namespace App\Security\PasswordReset;

use App\Entity\User;
use App\Repository\UserRepository;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\PropertyAccess\PropertyAccessorInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Signature\Exception\ExpiredSignatureException;
use Symfony\Component\Security\Core\Signature\Exception\InvalidSignatureException;
use Symfony\Component\Security\Core\Signature\SignatureHasher;

/**
 * Creates and checks the links of "forgot my password" emails.
 *
 * Nothing is stored: a link carries the user's id, the time it expires and
 * a signature over both plus the user's email and current password hash,
 * made with a key only this installation has. So a link
 *
 *  - cannot be made or altered by anyone else,
 *  - stops working after {@see self::LIFETIME_MINUTES},
 *  - works once: choosing a new password changes the hash it was signed
 *    over (and so does anyone else changing the password or the email).
 */
final class PasswordResetLinks
{
    public const ROUTE = 'app_password_reset_confirm';
    public const LIFETIME_MINUTES = 60;

    private readonly SignatureHasher $hasher;

    public function __construct(
        #[Autowire('%kernel.secret%')]
        #[\SensitiveParameter]
        string $secret,
        PropertyAccessorInterface $propertyAccessor,
        private readonly UserRepository $users,
        private readonly UrlGeneratorInterface $urlGenerator,
    ) {
        // A key of its own, so that a signature made here is of no use
        // anywhere else the application secret signs something.
        $this->hasher = new SignatureHasher($propertyAccessor, ['id', 'password'], hash_hmac('sha256', 'password-reset', $secret));
    }

    /**
     * @param \DateTimeInterface|null $expiresAt for tests; a link is otherwise valid for LIFETIME_MINUTES from now
     */
    public function createUrl(User $user, ?\DateTimeInterface $expiresAt = null): string
    {
        $expires = $expiresAt?->getTimestamp() ?? time() + self::LIFETIME_MINUTES * 60;

        return $this->urlGenerator->generate(self::ROUTE, [
            'id' => $user->getId(),
            'expires' => $expires,
            'signature' => $this->hasher->computeSignatureHash($user, $expires),
        ], UrlGeneratorInterface::ABSOLUTE_URL);
    }

    /**
     * The account a link is for, or null when the link is not (or no
     * longer) good: altered, expired, already used, or for an account that
     * has been deactivated or deleted since.
     */
    public function resolve(int $userId, int $expires, string $signature): ?User
    {
        $user = $this->users->find($userId);
        if (!$user instanceof User || !$user->isActive()) {
            return null;
        }

        try {
            $this->hasher->verifySignatureHash($user, $expires, $signature);
        } catch (InvalidSignatureException|ExpiredSignatureException) {
            return null;
        }

        return $user;
    }
}
