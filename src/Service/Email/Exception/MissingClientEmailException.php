<?php

declare(strict_types=1);

namespace App\Service\Email\Exception;

/**
 * Thrown when an email cannot be sent because the related client has no
 * email address on file.
 */
class MissingClientEmailException extends \RuntimeException
{
}
