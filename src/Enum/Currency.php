<?php

namespace App\Enum;

/**
 * Supported currencies. Stored on entities as ISO 4217 codes (VARCHAR(3)).
 *
 * The enum is the runtime view of the data: persistence uses strings so old
 * rows with codes not (yet / no longer) listed here never become invalid.
 *
 * Symbol selection rule: prefer a widely recognised single-character symbol
 * when it is unambiguous. For currencies that share the same glyph (e.g. ¥ is
 * both JPY and CNY) or have no compact symbol (CHF, CAD, AUD), fall back to
 * the ISO code so the displayed amount can never be confused with another
 * currency.
 */
enum Currency: string
{
    case Eur = 'EUR';
    case Usd = 'USD';
    case Gbp = 'GBP';
    case Chf = 'CHF';
    case Cad = 'CAD';
    case Aud = 'AUD';
    case Jpy = 'JPY';
    case Cny = 'CNY';
    case Pln = 'PLN';
    case Ron = 'RON';

    public const DEFAULT = self::Eur;

    /**
     * Display symbol for this currency. Placed at the end of amounts by the
     * formatter.
     */
    public function symbol(): string
    {
        return match ($this) {
            self::Eur => '€',
            self::Usd => '$',
            self::Gbp => '£',
            self::Chf => 'CHF',
            self::Cad => 'CAD',
            self::Aud => 'AUD',
            self::Jpy => '¥',
            self::Cny => 'CNY',
            self::Pln => 'zł',
            self::Ron => 'lei',
        };
    }

    /**
     * @return array<string, string> map of "CODE (symbol)" => stored value
     * for form choices.
     */
    public static function choices(): array
    {
        $choices = [];
        foreach (self::cases() as $case) {
            $choices[sprintf('%s (%s)', $case->value, $case->symbol())] = $case->value;
        }

        return $choices;
    }

    /**
     * @return list<string> list of valid ISO codes for Assert\Choice
     */
    public static function values(): array
    {
        return array_map(fn (self $c) => $c->value, self::cases());
    }
}
