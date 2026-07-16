<?php

declare(strict_types=1);

namespace App\Twig;

use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;
use Twig\TwigTest;

class DiscountExtension extends AbstractExtension
{
    public function getTests(): array
    {
        return [
            new TwigTest('discounted', [$this, 'isDiscounted']),
        ];
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('has_discounts', [$this, 'hasDiscounts']),
        ];
    }

    public function isDiscounted(string|float|int|null $amount): bool
    {
        if ($amount === null || $amount === '') {
            return false;
        }

        return (float) $amount > 0;
    }

    /**
     * @param iterable<object> $items items exposing getDiscountAmount(): string|float|int|null
     */
    public function hasDiscounts(iterable $items): bool
    {
        foreach ($items as $item) {
            if (\is_object($item) && method_exists($item, 'getDiscountAmount')
                && $this->isDiscounted($item->getDiscountAmount())) {
                return true;
            }
        }

        return false;
    }
}
