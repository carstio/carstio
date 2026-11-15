<?php

declare(strict_types=1);

namespace App\Security\PasswordReset;

use App\Entity\User;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;

/**
 * Limits how often a reset link can be asked for: per visitor, so the form
 * cannot be used to probe addresses or to send email in bulk, and per
 * address, so nobody's inbox can be flooded from many places at once.
 *
 * The limits themselves are in config/packages/rate_limiter.yaml.
 */
final class PasswordResetThrottle
{
    public function __construct(
        private readonly RateLimiterFactoryInterface $passwordResetIpLimiter,
        private readonly RateLimiterFactoryInterface $passwordResetEmailLimiter,
    ) {
    }

    /**
     * Counts this attempt and says whether it may go ahead. An address is
     * counted whether or not an account exists for it, so being refused
     * tells nothing about the account.
     */
    public function allows(?string $clientIp, string $email): bool
    {
        $byVisitor = $this->passwordResetIpLimiter->create($clientIp ?? 'unknown')->consume();
        if (!$byVisitor->isAccepted()) {
            return false;
        }

        return $this->passwordResetEmailLimiter->create(User::normalizeEmail($email))->consume()->isAccepted();
    }
}
