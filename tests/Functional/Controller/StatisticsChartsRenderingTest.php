<?php

declare(strict_types=1);

namespace App\Tests\Functional\Controller;

use App\Repository\UserRepository;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\DomCrawler\Crawler;

/**
 * Verifies the Statistics page server-side chart pipeline:
 * - the importmap exposes Chart.js so the Stimulus controller can boot
 * - each chart card emits a <canvas> + the statistics-chart Stimulus binding
 * - the data-statistics-chart-config-value JSON has the contract the JS expects
 *
 * The browser-side rendering of the Chart.js canvas itself is not exercised
 * here: PHPUnit cannot evaluate JavaScript. These assertions cover the
 * server-side preconditions, which is where this feature has historically
 * regressed (e.g. when stale public/assets/ hides newly added controllers).
 */
class StatisticsChartsRenderingTest extends WebTestCase
{
    private function loginAdmin(): KernelBrowser
    {
        $client = static::createClient();
        $user = static::getContainer()->get(UserRepository::class)->findOneBy(['email' => 'admin@carstio.local']);
        $client->loginUser($user);

        return $client;
    }

    public function testAllFourChartCardsAreRendered(): void
    {
        $client = $this->loginAdmin();
        $crawler = $client->request('GET', '/statistics?range=this_month');
        $this->assertResponseIsSuccessful();

        $titles = $crawler->filter('.statistics-charts-grid .section-card-title')->each(
            fn (Crawler $node) => trim($node->text()),
        );

        $this->assertContains('Revenue over time', $titles);
        $this->assertContains('Work orders by status', $titles);
        $this->assertContains('Offers by status', $titles);
        $this->assertContains('Payments by method', $titles);
    }

    public function testEachChartContainerCarriesStimulusBindings(): void
    {
        $client = $this->loginAdmin();
        $crawler = $client->request('GET', '/statistics?range=this_month');
        $this->assertResponseIsSuccessful();

        $charts = $crawler->filter('.statistics-charts-grid [data-controller="statistics-chart"]');

        // With current fixtures every chart has at least one data point.
        $this->assertSame(4, $charts->count(), 'Expected all four charts to be rendered with statistics-chart Stimulus controller.');

        foreach ($charts as $node) {
            $crawl = new Crawler($node);
            $this->assertCount(1, $crawl->filter('canvas[data-statistics-chart-target="canvas"]'));

            $config = $crawl->attr('data-statistics-chart-config-value');
            $this->assertNotNull($config, 'chart container must expose its config to Stimulus.');
            $decoded = json_decode($config, true);
            $this->assertIsArray($decoded);
            $this->assertContains($decoded['type'], ['line', 'bar', 'doughnut']);
            $this->assertIsArray($decoded['labels']);
            $this->assertIsArray($decoded['values']);
            $this->assertSameSize($decoded['labels'], $decoded['values']);
            $this->assertNotEmpty($decoded['labels'], 'rendered chart should not be empty when fixtures provide data.');
        }
    }

    public function testImportmapExposesChartJsForStimulusController(): void
    {
        $client = $this->loginAdmin();
        $client->request('GET', '/statistics?range=this_month');
        $this->assertResponseIsSuccessful();

        $html = (string) $client->getResponse()->getContent();
        preg_match('#<script type="importmap"[^>]*>(.*?)</script>#s', $html, $matches);
        $this->assertNotEmpty($matches, 'Base layout must render a Stimulus importmap.');

        $importmap = json_decode($matches[1], true);
        $this->assertIsArray($importmap);
        $this->assertArrayHasKey('imports', $importmap);

        $this->assertArrayHasKey('chart.js/auto', $importmap['imports'], 'Chart.js must be in the importmap so the Stimulus controller can import it.');
        $this->assertArrayHasKey(
            '/assets/controllers/statistics_chart_controller.js',
            $importmap['imports'],
            'statistics_chart_controller must be discovered by Stimulus auto-loader.',
        );
    }

    public function testEmptyChartShowsFallbackInsteadOfBrokenCanvas(): void
    {
        $client = $this->loginAdmin();

        // A future-only date range yields no work orders / offers / payments.
        $crawler = $client->request('GET', '/statistics?range=custom&from=2099-01-01&to=2099-01-31');
        $this->assertResponseIsSuccessful();

        $cards = $crawler->filter('.statistics-charts-grid .section-card');
        $this->assertGreaterThanOrEqual(1, $cards->count());

        $emptyMessages = $crawler->filter('.statistics-charts-grid .section-card .text-muted')->each(
            fn (Crawler $node) => trim($node->text()),
        );

        $this->assertContains('No data for the selected period.', $emptyMessages);
    }
}
