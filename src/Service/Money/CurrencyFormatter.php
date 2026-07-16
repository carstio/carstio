<?php

namespace App\Service\Money;

use App\Enum\Currency;

/**
 * Formats monetary amounts with the currency symbol at the end.
 *
 * Kept intentionally tiny: templates and controllers use this instead of
 * concatenating strings, which keeps rendering logic consistent and makes
 * later changes (e.g. locale-aware number formatting) a one-file edit.
 */
class CurrencyFormatter
{
    public function format(string|float|int|null $amount, ?string $currencyCode): string
    {
        $number = number_format($this->toFloat($amount), 2, '.', '');
        $suffix = $this->suffixFor($currencyCode);

        return $number . ' ' . $suffix;
    }

    private function suffixFor(?string $currencyCode): string
    {
        if ($currencyCode === null || $currencyCode === '') {
            return Currency::DEFAULT->symbol();
        }

        $currency = Currency::tryFrom($currencyCode);
        if ($currency !== null) {
            return $currency->symbol();
        }

        return $currencyCode;
    }

    private function toFloat(string|float|int|null $value): float
    {
        if ($value === null || $value === '') {
            return 0.0;
        }

        return (float) $value;
    }
}
