<?php

declare(strict_types=1);

namespace App\Tests\Support\Email;

use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\TransportInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\RawMessage;

/**
 * Test-only transport that captures sent messages instead of dispatching
 * them. Stores the {@see Email} (or RawMessage) into a static buffer so
 * controller tests can inspect the composed email without touching SMTP.
 */
class RecordingTransport implements TransportInterface
{
    /** @var list<Email|RawMessage> */
    private static array $sent = [];

    public function send(RawMessage $message, ?Envelope $envelope = null): SentMessage
    {
        self::$sent[] = $message;

        $envelope ??= Envelope::create($message);

        return new SentMessage($message, $envelope);
    }

    public function __toString(): string
    {
        return 'recording://test';
    }

    /**
     * @return list<Email|RawMessage>
     */
    public static function sent(): array
    {
        return self::$sent;
    }

    public static function reset(): void
    {
        self::$sent = [];
    }
}
