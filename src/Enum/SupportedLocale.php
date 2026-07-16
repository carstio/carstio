<?php

namespace App\Enum;

/**
 * Locales the UI is shipped translated for.
 *
 * The enum is the runtime source of truth; persistence (User.preferredLocale,
 * settings, session) keeps plain strings so unknown values never crash the app
 * and can be ignored gracefully.
 */
enum SupportedLocale: string
{
    case En = 'en';
    case Bg = 'bg';
    case De = 'de';
    case Es = 'es';
    case Fr = 'fr';

    public const DEFAULT = self::En;

    /**
     * Native (endonym) name used in language pickers. Each language is
     * presented in its own script so users recognise their language even
     * when the UI is currently in a different one.
     */
    public function nativeName(): string
    {
        return match ($this) {
            self::En => 'English',
            self::Bg => 'Български',
            self::De => 'Deutsch',
            self::Es => 'Español',
            self::Fr => 'Français',
        };
    }

    /**
     * @return array<string, string> map of native name => locale code, used
     * by Symfony Form ChoiceType.
     */
    public static function choices(): array
    {
        $choices = [];
        foreach (self::cases() as $case) {
            $choices[$case->nativeName()] = $case->value;
        }

        return $choices;
    }

    /**
     * @return list<string> list of valid locale codes for Assert\Choice
     */
    public static function values(): array
    {
        return array_map(fn (self $c) => $c->value, self::cases());
    }

    public static function isSupported(?string $code): bool
    {
        return $code !== null && self::tryFrom($code) !== null;
    }
}
