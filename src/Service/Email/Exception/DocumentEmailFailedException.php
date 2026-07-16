<?php

declare(strict_types=1);

namespace App\Service\Email\Exception;

/**
 * Generic envelope thrown when SMTP transport rejected the message at send
 * time. The original transport exception is preserved as $previous so it
 * can be logged without being shown to end users.
 */
class DocumentEmailFailedException extends \RuntimeException
{
}
