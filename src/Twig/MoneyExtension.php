<?php

namespace App\Twig;

use App\Service\Money\CurrencyFormatter;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

class MoneyExtension extends AbstractExtension
{
    public function __construct(
        private readonly CurrencyFormatter $formatter,
    ) {
    }

    public function getFilters(): array
    {
        return [
            new TwigFilter('money', [$this, 'money']),
        ];
    }

    public function money(string|float|int|null $amount, ?string $currencyCode): string
    {
        return $this->formatter->format($amount, $currencyCode);
    }
}
