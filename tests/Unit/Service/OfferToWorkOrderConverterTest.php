<?php

namespace App\Tests\Unit\Service;

use App\Entity\Car\Car;
use App\Entity\Client\Client;
use App\Entity\Sales\Offer;
use App\Entity\Sales\OfferItem;
use App\Entity\User;
use App\Entity\Workshop\WorkOrder;
use App\Enum\OfferItemType;
use App\Enum\OfferStatus;
use App\Enum\WorkOrderItemType;
use App\Service\OfferToWorkOrderConverter;
use App\Service\Settings\SettingsProvider;
use App\Service\WorkOrderCalculator;
use App\Service\WorkOrderFactory;
use App\Service\WorkOrderNumberGenerator;
use App\Settings\SettingsRegistry;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

class OfferToWorkOrderConverterTest extends TestCase
{
    private function makeConverter(EntityManagerInterface $em, string $settingsCurrency = 'EUR'): OfferToWorkOrderConverter
    {
        $numberGenerator = $this->createMock(WorkOrderNumberGenerator::class);
        $numberGenerator->method('generate')->willReturn('WO-2026-9001');

        $settings = $this->createMock(SettingsProvider::class);
        $settings->method('getString')
            ->with(SettingsRegistry::SECTION_CURRENCY, SettingsRegistry::KEY_DEFAULT_CURRENCY_CODE)
            ->willReturn($settingsCurrency);

        return new OfferToWorkOrderConverter(
            $em,
            new WorkOrderFactory($numberGenerator, $settings),
            new WorkOrderCalculator(),
        );
    }

    private function makeApprovedOffer(): Offer
    {
        $user = (new User())->setEmail('creator@carstio.local')->setFullName('Creator')->setRoles(['ROLE_USER']);
        $client = new Client();
        $client->setName('John Doe');
        $client->setPhone('+1 555-0101');
        $car = (new Car())->setClient($client);

        $offer = new Offer();
        $offer->setNumber('OF-2026-1001');
        $offer->setClient($client);
        $offer->setCar($car);
        $offer->setCreatedBy($user);
        $offer->setTitle('Full service');
        $offer->setNotes('Agreed by phone.');
        $offer->setStatus(OfferStatus::Approved);
        // USD (not the default) so the test proves the converter snapshots
        // the offer's currency rather than reading the current settings.
        $offer->setCurrencyCode('USD');

        $laborItem = new OfferItem();
        $laborItem->setType(OfferItemType::Labor);
        $laborItem->setName('Labor');
        $laborItem->setQuantity('2.00');
        $laborItem->setUnitPrice('50.00');
        $laborItem->setSortOrder(0);
        $offer->addItem($laborItem);

        $partItem = new OfferItem();
        $partItem->setType(OfferItemType::Part);
        $partItem->setName('Oil Filter');
        $partItem->setQuantity('1.00');
        $partItem->setUnitPrice('8.00');
        $partItem->setSortOrder(1);
        $offer->addItem($partItem);

        return $offer;
    }

    public function testConvertCreatesWorkOrderAndLinksOffer(): void
    {
        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects($this->once())->method('persist')->with($this->isInstanceOf(WorkOrder::class));
        $em->expects($this->once())->method('flush');

        $converter = $this->makeConverter($em);
        $offer = $this->makeApprovedOffer();

        $triggerUser = (new User())->setEmail('converter@carstio.local')->setFullName('Converter')->setRoles(['ROLE_USER']);

        $workOrder = $converter->convert($offer, $triggerUser);

        $this->assertSame('WO-2026-9001', $workOrder->getNumber());
        $this->assertSame($offer->getClient(), $workOrder->getClient());
        $this->assertSame($offer->getCar(), $workOrder->getCar());
        $this->assertSame($triggerUser, $workOrder->getCreatedBy());
        $this->assertSame('Offer OF-2026-1001', $workOrder->getSource());
        $this->assertSame('Full service', $workOrder->getComplaint());
        $this->assertSame('Agreed by phone.', $workOrder->getNotes());

        $this->assertCount(2, $workOrder->getItems());
        $items = $workOrder->getItems()->toArray();
        $this->assertSame(WorkOrderItemType::Labor, $items[0]->getType());
        $this->assertSame('Labor', $items[0]->getName());
        $this->assertSame('2.00', $items[0]->getQuantity());
        $this->assertSame(WorkOrderItemType::Part, $items[1]->getType());
        $this->assertSame('Oil Filter', $items[1]->getName());

        $this->assertSame('108.00', $workOrder->getTotalAmount());

        $this->assertSame('USD', $workOrder->getCurrencyCode(), 'Converted work order must inherit the offer currency, not the current settings default.');

        $this->assertSame(OfferStatus::Converted, $offer->getStatus());
        $this->assertSame($workOrder, $offer->getWorkOrder());
        $this->assertNotNull($offer->getConvertedAt());
    }

    public function testCannotConvertUnapprovedOffer(): void
    {
        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects($this->never())->method('persist');
        $em->expects($this->never())->method('flush');

        $converter = $this->makeConverter($em);

        $offer = $this->makeApprovedOffer();
        $offer->setStatus(OfferStatus::Draft);

        $this->expectException(\DomainException::class);
        $converter->convert($offer, new User());
    }

    public function testCannotConvertTwice(): void
    {
        $em = $this->createMock(EntityManagerInterface::class);
        $converter = $this->makeConverter($em);

        $offer = $this->makeApprovedOffer();
        $user = (new User())->setEmail('t@example.com')->setFullName('T')->setRoles(['ROLE_USER']);

        $converter->convert($offer, $user);

        $this->expectException(\DomainException::class);
        $converter->convert($offer, $user);
    }

    public function testConvertCarriesCostPriceForwardForPartsMargin(): void
    {
        $em = $this->createMock(EntityManagerInterface::class);
        $converter = $this->makeConverter($em);
        $offer = $this->makeApprovedOffer();

        // Set a cost on the part line so the work order can later show
        // parts margin without losing the recorded purchase price.
        $partItem = $offer->getItems()->toArray()[1];
        $partItem->setCostPrice('3.50');

        $user = (new User())->setEmail('c@carstio.local')->setFullName('C')->setRoles(['ROLE_USER']);
        $workOrder = $converter->convert($offer, $user);

        $items = $workOrder->getItems()->toArray();
        $this->assertSame('3.50', $items[1]->getCostPrice());
        $this->assertNull($items[0]->getCostPrice(), 'Labor lines must not inherit a cost price.');
    }
}
