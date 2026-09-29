<?php

declare(strict_types=1);

namespace App\Tests\Functional\Service;

use App\Entity\Car\Car;
use App\Entity\Client\Client;
use App\Entity\Sales\Offer;
use App\Entity\Workshop\WorkOrder;
use App\Service\OfferNumberGenerator;
use App\Service\WorkOrderNumberGenerator;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * The generators derive the next sequence from the highest existing number
 * of the current year. Plain string ordering would rank "…-9999" above
 * "…-10000", so both generators must order by length first.
 */
class DocumentNumberGeneratorTest extends KernelTestCase
{
    public function testOfferNumbersKeepCountingPastFourDigits(): void
    {
        self::bootKernel();
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $year = date('Y');

        foreach (['9999', '10000'] as $sequence) {
            $offer = new Offer();
            $offer->setNumber(sprintf('OF-%s-%s', $year, $sequence));
            $offer->setClient($this->client($em));
            $offer->setCar($this->car($em));
            $em->persist($offer);
        }
        $em->flush();

        $next = static::getContainer()->get(OfferNumberGenerator::class)->generate();

        $this->assertSame(sprintf('OF-%s-10001', $year), $next);
    }

    public function testWorkOrderNumbersKeepCountingPastFourDigits(): void
    {
        self::bootKernel();
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $year = date('Y');

        foreach (['9999', '10000'] as $sequence) {
            $workOrder = new WorkOrder();
            $workOrder->setNumber(sprintf('WO-%s-%s', $year, $sequence));
            $workOrder->setClient($this->client($em));
            $workOrder->setCar($this->car($em));
            $workOrder->setComplaint('Sequence test');
            $em->persist($workOrder);
        }
        $em->flush();

        $next = static::getContainer()->get(WorkOrderNumberGenerator::class)->generate();

        $this->assertSame(sprintf('WO-%s-10001', $year), $next);
    }

    private function client(EntityManagerInterface $em): Client
    {
        $client = $em->getRepository(Client::class)->findOneBy([]);
        self::assertNotNull($client, 'Fixtures must provide at least one client.');

        return $client;
    }

    private function car(EntityManagerInterface $em): Car
    {
        $car = $em->getRepository(Car::class)->findOneBy([]);
        self::assertNotNull($car, 'Fixtures must provide at least one car.');

        return $car;
    }
}
