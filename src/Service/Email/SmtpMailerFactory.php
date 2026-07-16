<?php

declare(strict_types=1);

namespace App\Service\Email;

use App\Enum\SmtpEncryption;
use App\Service\Email\Exception\IncompleteSmtpSettingsException;
use App\Service\Settings\SettingsProvider;
use App\Settings\SettingsRegistry;
use Symfony\Component\Mailer\Mailer;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mailer\Transport\Smtp\EsmtpTransport;
use Symfony\Component\Mime\Address;

/**
 * Builds a {@see MailerInterface} that sends through the SMTP transport
 * configured in Settings → Email.
 *
 * Each call returns a fresh, single-shot Mailer so the SMTP credentials
 * are never cached across requests, and so a settings change takes effect
 * immediately. Throws {@see IncompleteSmtpSettingsException} when the
 * required fields are missing - controllers turn that into a translated
 * flash message, never expose it to the user as a stack trace.
 */
class SmtpMailerFactory
{
    public function __construct(
        private readonly SettingsProvider $settings,
    ) {
    }

    public function create(): MailerInterface
    {
        return new Mailer($this->buildTransport());
    }

    /**
     * Resolves the From address based on Settings → Email and falls back
     * to the company name when the dedicated SMTP "from name" is empty.
     */
    public function fromAddress(): Address
    {
        $email = trim((string) $this->settings->getString(
            SettingsRegistry::SECTION_EMAIL,
            SettingsRegistry::KEY_SMTP_FROM_EMAIL,
        ));
        if ($email === '' || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            throw new IncompleteSmtpSettingsException('A valid From email address is required.');
        }

        $name = trim((string) $this->settings->getString(
            SettingsRegistry::SECTION_EMAIL,
            SettingsRegistry::KEY_SMTP_FROM_NAME,
        ));
        if ($name === '') {
            $name = trim((string) $this->settings->getString(
                SettingsRegistry::SECTION_COMPANY,
                SettingsRegistry::KEY_COMPANY_NAME,
            ));
        }

        return new Address($email, $name);
    }

    private function buildTransport(): EsmtpTransport
    {
        $host = trim((string) $this->settings->getString(
            SettingsRegistry::SECTION_EMAIL,
            SettingsRegistry::KEY_SMTP_HOST,
        ));
        $port = $this->settings->getInt(
            SettingsRegistry::SECTION_EMAIL,
            SettingsRegistry::KEY_SMTP_PORT,
        );
        $encryption = SmtpEncryption::tryFrom((string) $this->settings->getString(
            SettingsRegistry::SECTION_EMAIL,
            SettingsRegistry::KEY_SMTP_ENCRYPTION,
        )) ?? SmtpEncryption::None;

        if ($host === '' || $port === null || $port < 1 || $port > 65535) {
            throw new IncompleteSmtpSettingsException('SMTP host and port are required.');
        }

        // EsmtpTransport's $tls flag means *implicit* TLS (smtps, e.g.
        // port 465) - only the "SSL" choice maps to it. The "TLS" choice
        // means STARTTLS (e.g. port 587): the connection starts in
        // plaintext and EsmtpTransport upgrades it automatically when the
        // server advertises STARTTLS, so $tls must stay false there.
        $implicitTls = $encryption === SmtpEncryption::Ssl;

        $transport = new EsmtpTransport($host, $port, $implicitTls);

        if ($encryption === SmtpEncryption::Tls) {
            // Fail closed: if the server never offers STARTTLS, abort
            // instead of silently sending credentials in plaintext.
            $transport->setRequireTls(true);
        }

        $username = trim((string) $this->settings->getString(
            SettingsRegistry::SECTION_EMAIL,
            SettingsRegistry::KEY_SMTP_USERNAME,
        ));
        if ($username !== '') {
            $transport->setUsername($username);
            $password = $this->settings->decryptSecret(
                SettingsRegistry::SECTION_EMAIL,
                SettingsRegistry::KEY_SMTP_PASSWORD,
            );
            if (is_string($password) && $password !== '') {
                $transport->setPassword($password);
            }
        }

        return $transport;
    }
}
