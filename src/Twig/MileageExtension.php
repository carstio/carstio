<?php

declare(strict_types=1);

namespace App\Twig;

use App\Entity\Workshop\WorkOrder;
use App\Service\Formatting\MileageFormatter;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

class MileageExtension extends AbstractExtension
{
    public function __construct(
        private readonly MileageFormatter $formatter,
    ) {
    }

    public function getFilters(): array
    {
        return [
            new TwigFilter('app_mileage', [$this, 'mileage']),
        ];
    }

    public function mileage(WorkOrder|null $workOrder): string
    {
        if ($workOrder === null) {
            return '';
        }

        return $this->formatter->format($workOrder);
    }
}
