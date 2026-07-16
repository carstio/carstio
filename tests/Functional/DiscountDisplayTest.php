<?php

namespace App\Tests\Functional;

use App\Repository\Sales\OfferRepository;
use App\Repository\UserRepository;
use App\Repository\Workshop\WorkOrderRepository;
use App\Service\OfferCalculator;
use App\Service\WorkOrderCalculator;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * End-to-end checks that zero-discount documents do not render discount column/row,
 * and that documents with a real discount do render them.
 */
class DiscountDisplayTest extends WebTestCase
{
    private function loginClient(): KernelBrowser
    {
        $client = static::createClient();
        $user = static::getContainer()->get(UserRepository::class)->findOneBy(['email' => 'admin@carstio.local']);
        $client->loginUser($user);

        return $client;
    }

    public function testOfferShowOmitsDiscountColumnAndRowWhenZero(): void
    {
        $client = $this->loginClient();

        $offer = static::getContainer()->get(OfferRepository::class)->findOneBy(['number' => 'OF-2026-0001']);
        $this->assertNotNull($offer);

        $crawler = $client->request('GET', '/offers/' . $offer->getId());
        $this->assertResponseIsSuccessful();

        $itemsHeader = $crawler->filter('table.data-table thead')->first()->text();
        $this->assertStringNotContainsString('Discount', $itemsHeader);

        $totals = $crawler->filter('.totals-row')->each(fn ($n) => $n->text());
        foreach ($totals as $row) {
            $this->assertStringNotContainsString('Discount', $row);
        }
    }

    public function testOfferShowDisplaysDiscountColumnAndRowWhenPresent(): void
    {
        $client = $this->loginClient();

        $em = static::getContainer()->get(EntityManagerInterface::class);
        $calculator = static::getContainer()->get(OfferCalculator::class);
        $offer = static::getContainer()->get(OfferRepository::class)->findOneBy(['number' => 'OF-2026-0001']);
        $this->assertNotNull($offer);

        $offer->getItems()->first()->setDiscountAmount('5.00');
        $calculator->recalculate($offer);
        $em->flush();

        try {
            $crawler = $client->request('GET', '/offers/' . $offer->getId());
            $this->assertResponseIsSuccessful();

            $itemsHeader = $crawler->filter('table.data-table thead')->first()->text();
            $this->assertStringContainsString('Discount', $itemsHeader);

            $totalLabels = $crawler->filter('.totals-row > span:first-child')->each(fn ($n) => $n->text());
            $this->assertContains('Discount', $totalLabels);
        } finally {
            $offer->getItems()->first()->setDiscountAmount('0.00');
            $calculator->recalculate($offer);
            $em->flush();
        }
    }

    public function testWorkOrderShowOmitsDiscountColumnAndRowWhenZero(): void
    {
        $client = $this->loginClient();

        $workOrder = static::getContainer()->get(WorkOrderRepository::class)->findOneBy(['number' => 'WO-2026-0001']);
        $this->assertNotNull($workOrder);

        $crawler = $client->request('GET', '/work-orders/' . $workOrder->getId());
        $this->assertResponseIsSuccessful();

        $itemsHeader = $crawler->filter('table.data-table thead')->first()->text();
        $this->assertStringNotContainsString('Discount', $itemsHeader);

        $totals = $crawler->filter('.totals-row')->each(fn ($n) => $n->text());
        foreach ($totals as $row) {
            $this->assertStringNotContainsString('Discount', $row);
        }
    }

    public function testWorkOrderShowDisplaysDiscountColumnAndRowWhenPresent(): void
    {
        $client = $this->loginClient();

        $em = static::getContainer()->get(EntityManagerInterface::class);
        $calculator = static::getContainer()->get(WorkOrderCalculator::class);
        $workOrder = static::getContainer()->get(WorkOrderRepository::class)->findOneBy(['number' => 'WO-2026-0001']);
        $this->assertNotNull($workOrder);

        $workOrder->getItems()->first()->setDiscountAmount('7.50');
        $calculator->recalculate($workOrder);
        $em->flush();

        try {
            $crawler = $client->request('GET', '/work-orders/' . $workOrder->getId());
            $this->assertResponseIsSuccessful();

            $itemsHeader = $crawler->filter('table.data-table thead')->first()->text();
            $this->assertStringContainsString('Discount', $itemsHeader);

            $totalLabels = $crawler->filter('.totals-row > span:first-child')->each(fn ($n) => $n->text());
            $this->assertContains('Discount', $totalLabels);
        } finally {
            $workOrder->getItems()->first()->setDiscountAmount('0.00');
            $calculator->recalculate($workOrder);
            $em->flush();
        }
    }
}
