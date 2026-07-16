<?php

declare(strict_types=1);

namespace App\Service\Statistics;

use App\Enum\OfferStatus;
use App\Enum\PaymentMethod;
use App\Enum\WorkOrderStatus;
use App\Repository\Sales\OfferRepository;
use App\Repository\Workshop\PaymentRepository;
use App\Repository\Workshop\WorkOrderItemRepository;
use App\Repository\Workshop\WorkOrderRepository;
use App\Service\Money\CurrencyFormatter;
use App\Service\Settings\SettingsProvider;
use App\Service\Statistics\View\ChartDataView;
use App\Service\Statistics\View\KpiCardView;
use App\Service\Statistics\View\MechanicWorkloadStatsView;
use App\Service\Statistics\View\PartsProfitStatsView;
use App\Service\Statistics\View\StatisticsDashboardView;
use App\Service\Statistics\View\TopClientStatsView;
use App\Service\Statistics\View\TopProfitablePartStatsView;
use App\Settings\SettingsRegistry;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Builds the statistics dashboard view for a resolved period.
 *
 * The service owns *all* aggregation logic - the controller simply hands
 * over a {@see StatisticsPeriod} and renders whatever this returns. That
 * keeps the controller thin and lets the dashboard be regression-tested
 * without booting the HTTP layer.
 *
 * Currency strategy: every monetary value on the dashboard is restricted
 * to a single currency (the workshop's configured default). When other
 * currencies appear on documents in the same period, the view exposes
 * them via {@see StatisticsDashboardView::$otherCurrencyCodes} so the
 * template can warn the user instead of mixing totals silently.
 */
final class StatisticsService
{
    private const TOP_CLIENTS_LIMIT = 5;
    private const TOP_PROFITABLE_PARTS_LIMIT = 5;
    private const REVENUE_BUCKETS_TARGET = 20;

    public function __construct(
        private readonly WorkOrderRepository $workOrderRepository,
        private readonly OfferRepository $offerRepository,
        private readonly PaymentRepository $paymentRepository,
        private readonly WorkOrderItemRepository $workOrderItemRepository,
        private readonly SettingsProvider $settings,
        private readonly CurrencyFormatter $currencyFormatter,
        private readonly TranslatorInterface $translator,
    ) {
    }

    public function buildDashboard(StatisticsPeriod $period): StatisticsDashboardView
    {
        $currency = $this->resolveDefaultCurrency();
        $allCurrencies = $this->workOrderRepository->distinctCurrenciesForPeriod($period->start, $period->end);
        $otherCurrencies = array_values(array_filter(
            $allCurrencies,
            static fn (string $code) => $code !== $currency,
        ));

        $totalRevenue = $this->workOrderRepository->sumTotalsForPeriod($period->start, $period->end, $currency);
        $paidAmount = $this->workOrderRepository->sumPaidForPeriod($period->start, $period->end, $currency);
        $unpaidAmount = number_format(
            max(0.0, (float) $totalRevenue - (float) $paidAmount),
            2,
            '.',
            '',
        );

        $statusCounts = $this->workOrderRepository->countByStatusForPeriod($period->start, $period->end);
        $completedCount = $this->workOrderRepository->countCompletedForPeriod($period->start, $period->end);
        $openCount = $this->workOrderRepository->countOpen();

        $offerCounts = $this->offerRepository->countByStatusForPeriod($period->start, $period->end);
        $conversion = $this->offerRepository->offerConversionForPeriod($period->start, $period->end);

        $partsProfit = $this->buildPartsProfit($period, $currency);

        $kpis = [
            new KpiCardView(
                labelKey: 'statistics.kpi.total_revenue',
                value: $this->currencyFormatter->format($totalRevenue, $currency),
                icon: 'cash',
                iconVariant: 'primary',
            ),
            new KpiCardView(
                labelKey: 'statistics.kpi.paid_amount',
                value: $this->currencyFormatter->format($paidAmount, $currency),
                icon: 'check',
                iconVariant: 'success',
            ),
            new KpiCardView(
                labelKey: 'statistics.kpi.unpaid_amount',
                value: $this->currencyFormatter->format($unpaidAmount, $currency),
                icon: 'warning',
                iconVariant: 'warning',
            ),
            new KpiCardView(
                labelKey: 'statistics.kpi.parts_revenue',
                value: $this->currencyFormatter->format($partsProfit->revenue, $currency),
                icon: 'part',
                iconVariant: 'primary',
            ),
            new KpiCardView(
                labelKey: 'statistics.kpi.parts_profit',
                value: $partsProfit->hasCostData
                    ? $this->currencyFormatter->format($partsProfit->profit, $currency)
                    : $this->translator->trans('statistics.value.not_available'),
                icon: 'cash',
                iconVariant: 'success',
                hint: $partsProfit->hasCostData
                    ? null
                    : $this->translator->trans('statistics.kpi.parts_profit.no_cost_hint'),
            ),
            new KpiCardView(
                labelKey: 'statistics.kpi.completed_work_orders',
                value: (string) $completedCount,
                icon: 'work-order',
                iconVariant: 'success',
            ),
            new KpiCardView(
                labelKey: 'statistics.kpi.open_work_orders',
                value: (string) $openCount,
                icon: 'clock',
                iconVariant: 'info',
                hint: $this->translator->trans('statistics.kpi.open_work_orders.hint'),
            ),
            new KpiCardView(
                labelKey: 'statistics.kpi.offer_conversion',
                value: $this->formatConversionRate($conversion['converted'], $conversion['nonDraft']),
                icon: 'tag',
                iconVariant: 'primary',
                hint: $this->translator->trans('statistics.kpi.offer_conversion.hint'),
            ),
        ];

        $charts = [
            'revenue_over_time' => $this->buildRevenueChart($period, $currency),
            'work_orders_by_status' => $this->buildWorkOrderStatusChart($statusCounts),
            'offers_by_status' => $this->buildOfferStatusChart($offerCounts),
            'payments_by_method' => $this->buildPaymentMethodChart($period, $currency),
        ];

        return new StatisticsDashboardView(
            period: $period,
            displayCurrencyCode: $currency,
            otherCurrencyCodes: $otherCurrencies,
            kpis: $kpis,
            charts: $charts,
            topClients: $this->buildTopClients($period, $currency),
            mechanicWorkload: $this->buildMechanicWorkload($period),
            partsProfit: $partsProfit,
            topProfitableParts: $this->buildTopProfitableParts($period, $currency),
        );
    }

    public function resolveDefaultCurrency(): string
    {
        $code = $this->settings->getString(
            SettingsRegistry::SECTION_CURRENCY,
            SettingsRegistry::KEY_DEFAULT_CURRENCY_CODE,
        );

        return $code !== null && $code !== '' ? $code : 'EUR';
    }

    /**
     * Public so the unit test for the conversion-rate edge cases can hit
     * it without booting the entire dashboard.
     */
    public function formatConversionRate(int $converted, int $nonDraft): string
    {
        if ($nonDraft <= 0) {
            return $this->translator->trans('statistics.value.not_available');
        }

        $rate = ($converted / $nonDraft) * 100.0;

        return number_format($rate, 1, '.', '') . '%';
    }

    private function buildRevenueChart(StatisticsPeriod $period, string $currency): ChartDataView
    {
        $rows = $this->workOrderRepository->findRevenueRowsForPeriod($period->start, $period->end, $currency);

        $granularity = $this->pickGranularity($period);
        $buckets = $this->emptyBuckets($period, $granularity);

        foreach ($rows as $row) {
            /** @var \DateTimeInterface $openedAt */
            $openedAt = $row['openedAt'];
            $key = $this->bucketKey($openedAt, $granularity);
            if (!isset($buckets[$key])) {
                continue;
            }
            $buckets[$key]['total'] += (float) $row['total'];
        }

        $labels = [];
        $values = [];
        foreach ($buckets as $bucket) {
            $labels[] = $bucket['label'];
            $values[] = round($bucket['total'], 2);
        }

        return new ChartDataView(
            type: $period->dayCount() <= 1 ? 'bar' : 'line',
            labels: $labels,
            values: $values,
            isMoney: true,
            currencyCode: $currency,
        );
    }

    /**
     * @param array<string, int> $statusCounts
     */
    private function buildWorkOrderStatusChart(array $statusCounts): ChartDataView
    {
        $labels = [];
        $values = [];
        foreach (WorkOrderStatus::cases() as $status) {
            $count = $statusCounts[$status->value] ?? 0;
            if ($count === 0) {
                continue;
            }
            $labels[] = $this->translator->trans('work_order.status.' . $status->value);
            $values[] = $count;
        }

        return new ChartDataView(type: 'doughnut', labels: $labels, values: $values);
    }

    /**
     * @param array<string, int> $statusCounts
     */
    private function buildOfferStatusChart(array $statusCounts): ChartDataView
    {
        $labels = [];
        $values = [];
        foreach (OfferStatus::cases() as $status) {
            $count = $statusCounts[$status->value] ?? 0;
            if ($count === 0) {
                continue;
            }
            $labels[] = $this->translator->trans('offer.status.' . $status->value);
            $values[] = $count;
        }

        return new ChartDataView(type: 'doughnut', labels: $labels, values: $values);
    }

    private function buildPaymentMethodChart(StatisticsPeriod $period, string $currency): ChartDataView
    {
        $sums = $this->paymentRepository->sumByMethodForPeriod($period->start, $period->end, $currency);

        $labels = [];
        $values = [];
        foreach (PaymentMethod::cases() as $method) {
            $sum = $sums[$method->value] ?? null;
            if ($sum === null || (float) $sum === 0.0) {
                continue;
            }
            $labels[] = $this->translator->trans('payment.method.' . $method->value);
            $values[] = round((float) $sum, 2);
        }

        return new ChartDataView(
            type: 'doughnut',
            labels: $labels,
            values: $values,
            isMoney: true,
            currencyCode: $currency,
        );
    }

    /**
     * @return list<TopClientStatsView>
     */
    private function buildTopClients(StatisticsPeriod $period, string $currency): array
    {
        $rows = $this->workOrderRepository->topClientsForPeriod(
            $period->start,
            $period->end,
            $currency,
            self::TOP_CLIENTS_LIMIT,
        );

        return array_map(
            static fn (array $row) => new TopClientStatsView(
                clientId: $row['clientId'],
                clientName: $row['clientName'],
                workOrderCount: $row['workOrderCount'],
                totalRevenue: $row['totalRevenue'],
                unpaidAmount: $row['unpaidAmount'],
            ),
            $rows,
        );
    }

    /**
     * @return list<MechanicWorkloadStatsView>
     */
    private function buildMechanicWorkload(StatisticsPeriod $period): array
    {
        $rows = $this->workOrderRepository->mechanicWorkloadForPeriod($period->start, $period->end);

        return array_map(
            static fn (array $row) => new MechanicWorkloadStatsView(
                userId: $row['userId'],
                fullName: $row['fullName'],
                activeWorkOrders: $row['activeWorkOrders'],
                completedInPeriod: $row['completedInPeriod'],
                totalAssigned: $row['totalAssigned'],
            ),
            $rows,
        );
    }

    private function buildPartsProfit(StatisticsPeriod $period, string $currency): PartsProfitStatsView
    {
        $sums = $this->workOrderItemRepository->sumPartsForPeriod(
            $period->start,
            $period->end,
            $currency,
        );

        $revenue = (float) $sums['revenue'];
        $cost = (float) $sums['cost'];
        $profit = $revenue - $cost;

        $margin = null;
        if ($sums['hasCostData'] && $revenue > 0.0) {
            $margin = number_format(($profit / $revenue) * 100.0, 1, '.', '');
        }

        return new PartsProfitStatsView(
            revenue: $sums['revenue'],
            cost: $sums['cost'],
            profit: number_format($profit, 2, '.', ''),
            marginPercent: $margin,
            hasCostData: $sums['hasCostData'],
        );
    }

    /**
     * @return list<TopProfitablePartStatsView>
     */
    private function buildTopProfitableParts(StatisticsPeriod $period, string $currency): array
    {
        $rows = $this->workOrderItemRepository->topProfitablePartsForPeriod(
            $period->start,
            $period->end,
            $currency,
            self::TOP_PROFITABLE_PARTS_LIMIT,
        );

        return array_map(
            static fn (array $row) => new TopProfitablePartStatsView(
                name: $row['name'],
                quantity: $row['quantity'],
                revenue: $row['revenue'],
                cost: $row['cost'],
                profit: $row['profit'],
                hasCostData: $row['hasCostData'],
            ),
            $rows,
        );
    }

    /**
     * Picks day / week / month so the revenue chart stays readable
     * regardless of the period length. Roughly 6-24 columns/points.
     */
    private function pickGranularity(StatisticsPeriod $period): string
    {
        $days = max(1, $period->dayCount());

        if ($days <= self::REVENUE_BUCKETS_TARGET * 1.5) {
            return 'day';
        }

        if ($days <= 120) {
            return 'week';
        }

        return 'month';
    }

    /**
     * @return array<string, array{label: string, total: float}>
     */
    private function emptyBuckets(StatisticsPeriod $period, string $granularity): array
    {
        $buckets = [];

        $cursor = $period->start;
        while ($cursor < $period->end) {
            $key = $this->bucketKey($cursor, $granularity);
            if (!isset($buckets[$key])) {
                $buckets[$key] = [
                    'label' => $this->bucketLabel($cursor, $granularity),
                    'total' => 0.0,
                ];
            }
            $cursor = $this->advance($cursor, $granularity);
        }

        return $buckets;
    }

    private function bucketKey(\DateTimeInterface $date, string $granularity): string
    {
        return match ($granularity) {
            'week' => $date->format('o-\WW'),
            'month' => $date->format('Y-m'),
            default => $date->format('Y-m-d'),
        };
    }

    private function bucketLabel(\DateTimeInterface $date, string $granularity): string
    {
        return match ($granularity) {
            'week' => 'W' . $date->format('W'),
            'month' => $date->format('M Y'),
            default => $date->format('M j'),
        };
    }

    private function advance(\DateTimeImmutable $cursor, string $granularity): \DateTimeImmutable
    {
        return match ($granularity) {
            'week' => $cursor->modify('+1 day'),
            'month' => $cursor->modify('+1 day'),
            default => $cursor->modify('+1 day'),
        };
    }
}
