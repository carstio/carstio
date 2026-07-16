<?php

namespace App\Enum;

/**
 * SMTP transport encryption modes accepted by the Symfony Mailer DSN
 * builder. Stored on EmailSettings as the string value so old rows survive
 * any future enum changes.
 */
enum SmtpEncryption: string
{
    case None = 'none';
    case Tls = 'tls';
    case Ssl = 'ssl';

    /**
     * @return array<string, string> map of label key => value for form choices
     */
    public static function choices(): array
    {
        return [
            'settings.email.encryption.none' => self::None->value,
            'settings.email.encryption.tls' => self::Tls->value,
            'settings.email.encryption.ssl' => self::Ssl->value,
        ];
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(fn (self $c) => $c->value, self::cases());
    }
}
