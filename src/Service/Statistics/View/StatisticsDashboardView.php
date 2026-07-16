<?php

declare(strict_types=1);

namespace App\Service\Statistics\View;

use App\Service\Statistics\StatisticsPeriod;

/**
 * Aggregated payload rendered by templates/statistics/index.html.twig.
 *
 * The controller hands this off to the template untouched; the template
 * iterates over its public collections and never calls services.
 */
final class StatisticsDashboardView
{
    /**
     * @param list<KpiCardView>                $kpis
     * @param array<string, ChartDataView>     $charts        keyed by chart slug
     * @param list<TopClientStatsView>         $topClients
     * @param list<MechanicWorkloadStatsView>  $mechanicWorkload
     * @param list<TopProfitablePartStatsView> $topProfitableParts
     * @param list<string>                     $otherCurrencyCodes currencies seen in period besides the displayed one
     */
    public function __construct(
        public readonly StatisticsPeriod $period,
        public readonly string $displayCurrencyCode,
        public readonly array $otherCurrencyCodes,
        public readonly array $kpis,
        public readonly array $charts,
        public readonly array $topClients,
        public readonly array $mechanicWorkload,
        public readonly PartsProfitStatsView $partsProfit,
        public readonly array $topProfitableParts,
    ) {
    }

    public function hasMixedCurrencies(): bool
    {
        return $this->otherCurrencyCodes !== [];
    }
}
