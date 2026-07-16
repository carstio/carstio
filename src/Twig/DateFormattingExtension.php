<?php

declare(strict_types=1);

namespace App\Twig;

use App\Service\Formatting\DateFormatter;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

class DateFormattingExtension extends AbstractExtension
{
    public function __construct(
        private readonly DateFormatter $formatter,
    ) {
    }

    public function getFilters(): array
    {
        return [
            new TwigFilter('app_date', [$this, 'date']),
            new TwigFilter('app_time', [$this, 'time']),
            new TwigFilter('app_datetime', [$this, 'datetime']),
        ];
    }

    public function date(?\DateTimeInterface $value): string
    {
        return $this->formatter->formatDate($value);
    }

    public function time(?\DateTimeInterface $value): string
    {
        return $this->formatter->formatTime($value);
    }

    public function datetime(?\DateTimeInterface $value): string
    {
        return $this->formatter->formatDateTime($value);
    }
}
