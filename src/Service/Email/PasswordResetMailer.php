<?php

declare(strict_types=1);

namespace App\Service\Email;

use App\Entity\User;
use App\Service\Email\Exception\IncompleteSmtpSettingsException;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;

/**
 * Sends the "choose a new password" email, through the SMTP account
 * configured in Settings → Email like every other email of the workshop.
 */
class PasswordResetMailer
{
    public function __construct(
        private readonly SmtpMailerFactory $mailerFactory,
        private readonly PasswordResetEmailFactory $emailFactory,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * Whether this installation can send email at all. Without it there is
     * no point in offering the form.
     */
    public function isAvailable(): bool
    {
        try {
            $this->mailerFactory->fromAddress();
            $this->mailerFactory->create();
        } catch (IncompleteSmtpSettingsException) {
            return false;
        }

        return true;
    }

    /**
     * Never throws: whoever asked for the reset must not be able to tell
     * from the response whether an email went out. Failures are logged.
     */
    public function send(User $user, string $resetUrl): void
    {
        try {
            $email = $this->emailFactory->build($user, $resetUrl, $this->mailerFactory->fromAddress());
            $this->mailerFactory->create()->send($email);
        } catch (IncompleteSmtpSettingsException|TransportExceptionInterface $e) {
            $this->logger->error('Password reset email could not be sent.', [
                'user_id' => $user->getId(),
                'exception' => $e,
            ]);
        }
    }
}
