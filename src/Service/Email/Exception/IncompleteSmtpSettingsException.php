<?php

declare(strict_types=1);

namespace App\Service\Email\Exception;

/**
 * Thrown when the SMTP settings or the From address are missing/invalid,
 * so the application cannot send the email at all.
 */
class IncompleteSmtpSettingsException extends \RuntimeException
{
}
