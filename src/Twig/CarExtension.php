<?php

declare(strict_types=1);

namespace App\Twig;

use App\Entity\Car\Car;
use App\Service\Formatting\CarLabelFormatter;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

class CarExtension extends AbstractExtension
{
    public function __construct(
        private readonly CarLabelFormatter $formatter,
    ) {
    }

    public function getFilters(): array
    {
        return [
            new TwigFilter('car_label', [$this, 'carLabel']),
        ];
    }

    public function carLabel(?Car $car): string
    {
        return $this->formatter->label($car);
    }
}
