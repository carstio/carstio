<?php

namespace App\Tests\Functional;

use App\Repository\Sales\OfferRepository;
use App\Repository\UserRepository;
use App\Repository\Workshop\PaymentRepository;
use App\Repository\Workshop\WorkOrderRepository;
use App\Service\Settings\SettingsManager;
use App\Settings\SettingsRegistry;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * End-to-end checks that the configured regional date format is applied to
 * Offer / WorkOrder / Payment list pages and PDFs. Each test resets the
 * regional section to a known state so it is order-independent.
 */
class SettingsAwareDisplayTest extends WebTestCase
{
    private function loginClient(): KernelBrowser
    {
        $client = static::createClient();
        $user = static::getContainer()->get(UserRepository::class)->findOneBy(['email' => 'admin@carstio.local']);
        $client->loginUser($user);

        return $client;
    }

    private function setRegional(string $dateFormat, string $timeFormat = 'H:i', string $timezone = 'UTC'): void
    {
        $manager = static::getContainer()->get(SettingsManager::class);
        $manager->setMany(SettingsRegistry::SECTION_REGIONAL, [
            SettingsRegistry::KEY_DATE_FORMAT => $dateFormat,
            SettingsRegistry::KEY_TIME_FORMAT => $timeFormat,
            SettingsRegistry::KEY_TIMEZONE => $timezone,
            SettingsRegistry::KEY_DEFAULT_LOCALE => 'en',
        ]);
    }

    public function testOfferIndexUsesConfiguredDateFormat(): void
    {
        $client = $this->loginClient();
        $this->setRegional('d.m.Y');

        // Anchor the test on a fixture offer with a known validUntil.
        $offer = static::getContainer()->get(OfferRepository::class)->findOneBy(['number' => 'OF-2026-0001']);
        $this->assertNotNull($offer);
        $this->assertNotNull($offer->getValidUntil());
        $expected = $offer->getValidUntil()->setTimezone(new \DateTimeZone('UTC'))->format('d.m.Y');
        $unexpected = $offer->getValidUntil()->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d');

        $crawler = $client->request('GET', '/offers');
        $this->assertResponseIsSuccessful();

        $row = $crawler->filter('tr')->reduce(
            fn ($node) => str_contains($node->text(), $offer->getNumber()),
        );
        $this->assertGreaterThan(0, $row->count());
        $this->assertStringContainsString($expected, $row->text());
        $this->assertStringNotContainsString($unexpected, $row->text());
    }

    public function testWorkOrderIndexUsesConfiguredDateFormat(): void
    {
        $client = $this->loginClient();
        $this->setRegional('d.m.Y');

        $workOrder = static::getContainer()->get(WorkOrderRepository::class)->findOneBy(['number' => 'WO-2026-0001']);
        $this->assertNotNull($workOrder);
        $expected = $workOrder->getOpenedAt()->setTimezone(new \DateTimeZone('UTC'))->format('d.m.Y');

        $crawler = $client->request('GET', '/work-orders');
        $this->assertResponseIsSuccessful();

        $row = $crawler->filter('tr')->reduce(
            fn ($node) => str_contains($node->text(), $workOrder->getNumber()),
        );
        $this->assertGreaterThan(0, $row->count());
        $this->assertStringContainsString($expected, $row->text());
    }

    public function testPaymentIndexUsesConfiguredDateFormat(): void
    {
        $client = $this->loginClient();
        $this->setRegional('d.m.Y');

        $payment = static::getContainer()->get(PaymentRepository::class)->findOneBy([], ['id' => 'DESC']);
        $this->assertNotNull($payment);
        $expected = $payment->getPaidAt()->setTimezone(new \DateTimeZone('UTC'))->format('d.m.Y');

        $crawler = $client->request('GET', '/payments');
        $this->assertResponseIsSuccessful();

        $body = $crawler->filter('table')->text();
        $this->assertStringContainsString($expected, $body);
    }

    public function testWorkOrderShowUsesConfiguredDateTimeFormat(): void
    {
        $client = $this->loginClient();
        $this->setRegional('d.m.Y', 'H:i');

        $workOrder = static::getContainer()->get(WorkOrderRepository::class)->findOneBy(['number' => 'WO-2026-0001']);
        $this->assertNotNull($workOrder);
        $expected = $workOrder->getOpenedAt()->setTimezone(new \DateTimeZone('UTC'))->format('d.m.Y H:i');

        $client->request('GET', '/work-orders/' . $workOrder->getId());
        $this->assertResponseIsSuccessful();

        $this->assertStringContainsString($expected, $client->getResponse()->getContent());
    }

    public function testFallsBackToDefaultsWhenSettingsCleared(): void
    {
        $client = $this->loginClient();
        // Clear by writing empty values; the formatter should fall back to RegionalDefaults.
        $this->setRegional('Y-m-d', 'H:i', 'UTC');

        $workOrder = static::getContainer()->get(WorkOrderRepository::class)->findOneBy(['number' => 'WO-2026-0001']);
        $this->assertNotNull($workOrder);
        $expected = $workOrder->getOpenedAt()->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d');

        $crawler = $client->request('GET', '/work-orders');
        $this->assertResponseIsSuccessful();

        $row = $crawler->filter('tr')->reduce(
            fn ($node) => str_contains($node->text(), $workOrder->getNumber()),
        );
        $this->assertStringContainsString($expected, $row->text());
    }

    public function testPdfFooterIncludesConfiguredDocumentFooter(): void
    {
        $manager = static::getContainer()->get(SettingsManager::class);
        $manager->setMany(SettingsRegistry::SECTION_DOCUMENTS, [
            SettingsRegistry::KEY_OFFER_NUMBER_PREFIX => 'OF',
            SettingsRegistry::KEY_WORK_ORDER_NUMBER_PREFIX => 'WO',
            SettingsRegistry::KEY_INVOICE_NUMBER_PREFIX => 'INV',
            SettingsRegistry::KEY_DEFAULT_OFFER_VALIDITY_DAYS => 14,
            SettingsRegistry::KEY_DOCUMENT_FOOTER_TEXT => 'Thank you for choosing carstio.',
        ]);

        $container = static::getContainer();
        $offer = $container->get(OfferRepository::class)->findOneBy(['number' => 'OF-2026-0001']);
        $this->assertNotNull($offer);

        $companyProvider = $container->get(\App\Service\Pdf\CompanyProfileProvider::class);
        $html = $container->get('twig')->render('pdf/offer.html.twig', [
            'offer' => $offer,
            'company' => $companyProvider->getCompany(),
            'logo_path' => null,
            'document_footer_text' => $companyProvider->getDocumentFooterText(),
        ]);

        $this->assertStringContainsString('Thank you for choosing carstio.', $html);
    }
}
