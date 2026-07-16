<?php

declare(strict_types=1);

namespace App\Service\Statistics\View;

/**
 * Plain payload consumed by the Stimulus chart controller.
 *
 * Kept JSON-serialisable on purpose: the template emits this as a
 * data-attribute and Chart.js renders client-side. Numeric data is
 * pre-aggregated in PHP - the JavaScript layer never queries the
 * database or computes business values.
 */
final class ChartDataView
{
    /**
     * @param 'bar'|'line'|'doughnut' $type
     * @param list<string>            $labels
     * @param list<float|int>         $values
     */
    public function __construct(
        public readonly string $type,
        public readonly array $labels,
        public readonly array $values,
        public readonly bool $isMoney = false,
        public readonly ?string $currencyCode = null,
    ) {
    }

    public function isEmpty(): bool
    {
        if ($this->labels === []) {
            return true;
        }

        foreach ($this->values as $v) {
            if ((float) $v !== 0.0) {
                return false;
            }
        }

        return true;
    }

    /**
     * @return array{type: string, labels: list<string>, values: list<float|int>, isMoney: bool, currencyCode: ?string}
     */
    public function toArray(): array
    {
        return [
            'type' => $this->type,
            'labels' => $this->labels,
            'values' => $this->values,
            'isMoney' => $this->isMoney,
            'currencyCode' => $this->currencyCode,
        ];
    }
}
