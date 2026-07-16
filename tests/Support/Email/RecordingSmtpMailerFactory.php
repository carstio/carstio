<?php

declare(strict_types=1);

namespace App\Tests\Support\Email;

use App\Service\Email\SmtpMailerFactory;
use Symfony\Component\Mailer\Mailer;
use Symfony\Component\Mailer\MailerInterface;

/**
 * Test override for {@see SmtpMailerFactory}: still goes through the
 * parent's settings validation (so "incomplete settings" errors keep
 * bubbling up) but swaps the SMTP transport for {@see RecordingTransport}
 * so no real network call is ever made.
 */
class RecordingSmtpMailerFactory extends SmtpMailerFactory
{
    public function create(): MailerInterface
    {
        // Parent validates settings and would throw on incomplete config.
        // We discard its EsmtpTransport (it doesn't connect on creation)
        // and return a recording-backed Mailer instead.
        parent::create();

        return new Mailer(new RecordingTransport());
    }
}
