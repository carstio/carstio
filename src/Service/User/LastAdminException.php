<?php

declare(strict_types=1);

namespace App\Service\User;

/**
 * Raised when an action would leave the system without any active admin.
 * Caught by the controller and surfaced as a translated flash message.
 */
final class LastAdminException extends \DomainException
{
}
