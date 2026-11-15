<?php

declare(strict_types=1);

namespace App\Security\Voter;

use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\AccessDecisionManagerInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * Who may see and edit what the workshop pays for parts, and with it the
 * profit and margin on them: administrators only.
 *
 * Templates, forms and controllers ask for {@see self::VIEW_COSTS} rather
 * than for a role, so the rule lives in this one place.
 *
 * @extends Voter<string, mixed>
 */
final class CostVoter extends Voter
{
    public const VIEW_COSTS = 'VIEW_COSTS';

    public function __construct(
        private readonly AccessDecisionManagerInterface $accessDecisionManager,
    ) {
    }

    protected function supports(string $attribute, mixed $subject): bool
    {
        return $attribute === self::VIEW_COSTS;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        return $this->accessDecisionManager->decide($token, ['ROLE_ADMIN']);
    }
}
