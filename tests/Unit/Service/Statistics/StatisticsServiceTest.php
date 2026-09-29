<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Statistics;

use App\Repository\Sales\OfferRepository;
use App\Repository\Workshop\PaymentRepository;
use App\Repository\Workshop\WorkOrderItemRepository;
use App\Repository\Workshop\WorkOrderRepository;
use App\Service\Money\CurrencyFormatter;
use App\Service\Settings\SettingsProvider;
use App\Service\Statistics\StatisticsService;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\Translation\TranslatorInterface;

class StatisticsServiceTest extends TestCase
{
    public function testConversionRateIsRoundedToOneDecimal(): void
    {
        $service = $this->buildService();

        $this->assertSame('25.0%', $service->formatConversionRate(1, 4));
    }

    public function testConversionRateFromMixedOfferStatuses(): void
    {
        $service = $this->buildService();

        // 2 converted, 5 non-draft (sent + approved + rejected + converted + converted)
        $this->assertSame('40.0%', $service->formatConversionRate(2, 5));
    }

    public function testConversionRateNotAvailableForZeroDenominator(): void
    {
        $translator = $this->createStub(TranslatorInterface::class);
        $translator->method('trans')->willReturnCallback(
            fn (string $id) => $id === 'statistics.value.not_available' ? 'N/A' : $id,
        );

        $service = $this->buildService($translator);

        $this->assertSame('N/A', $service->formatConversionRate(0, 0));
    }

    public function testConversionRateNotAvailableWhenAllOffersAreDrafts(): void
    {
        $translator = $this->createStub(TranslatorInterface::class);
        $translator->method('trans')->willReturnCallback(
            fn (string $id) => $id === 'statistics.value.not_available' ? 'N/A' : $id,
        );

        $service = $this->buildService($translator);

        // Converted = 0 because we ignore drafts. Denominator is 0 too.
        $this->assertSame('N/A', $service->formatConversionRate(0, 0));
    }

    private function buildService(?TranslatorInterface $translator = null): StatisticsService
    {
        $translator ??= $this->createStub(TranslatorInterface::class);
        $translator->method('trans')->willReturnCallback(static fn (string $id) => $id);

        return new StatisticsService(
            $this->createStub(WorkOrderRepository::class),
            $this->createStub(OfferRepository::class),
            $this->createStub(PaymentRepository::class),
            $this->createStub(WorkOrderItemRepository::class),
            $this->createStub(SettingsProvider::class),
            new CurrencyFormatter(),
            $translator,
        );
    }
}
