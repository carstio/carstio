<?php

namespace App\DataFixtures;

use App\Entity\Car\Car;
use App\Entity\Client\Client;
use App\Entity\Part\Part;
use App\Entity\User;
use App\Entity\Workshop\WorkOrder;
use App\Entity\Workshop\WorkOrderItem;
use App\Enum\MileageUnit;
use App\Enum\WorkOrderItemType;
use App\Enum\WorkOrderStatus;
use App\Service\WorkOrderCalculator;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;

class WorkOrderFixtures extends Fixture implements DependentFixtureInterface
{
    public const WO_JOHN_COROLLA = 'wo-john-corolla';
    public const WO_ALICE_X5 = 'wo-alice-x5';

    public function __construct(
        private readonly WorkOrderCalculator $calculator,
    ) {
    }

    public function load(ObjectManager $manager): void
    {
        $admin = $this->getReference(UserFixtures::ADMIN_REFERENCE, User::class);
        $mechanic = $this->getReference(UserFixtures::MECHANIC_REFERENCE, User::class);

        // Work order 1: John's Corolla - oil change + brake inspection
        $wo1 = new WorkOrder();
        $wo1->setNumber('WO-2026-0001');
        $wo1->setClient($this->getReference(ClientFixtures::CLIENT_JOHN, Client::class));
        $wo1->setCar($this->getReference(CarFixtures::CAR_JOHN_COROLLA, Car::class));
        $wo1->setStatus(WorkOrderStatus::InProgress);
        $wo1->setComplaint('Oil change due, brakes squeaking');
        $wo1->setDiagnosis('Oil change needed, front brake pads worn');
        $wo1->setSource('Phone call');
        $wo1->setStartedAt(new \DateTimeImmutable('-1 day'));
        $wo1->setCreatedBy($mechanic);
        // Latest service on John's Corolla - tops the increasing mileage
        // sequence below so the Car details page reflects realistic wear.
        $wo1->setOdometerReading(119800);
        $wo1->setOdometerUnit(MileageUnit::Kilometres);

        $item1 = new WorkOrderItem();
        $item1->setType(WorkOrderItemType::Labor);
        $item1->setName('Oil change');
        $item1->setQuantity('1.00');
        $item1->setUnitPrice('30.00');
        $item1->setSortOrder(0);
        $wo1->addItem($item1);

        $item2 = new WorkOrderItem();
        $item2->setType(WorkOrderItemType::Part);
        $item2->setName('Oil Filter');
        $item2->setPart($this->getReference(PartFixtures::PART_OIL_FILTER, Part::class));
        $item2->setQuantity('1.00');
        $item2->setUnitPrice('8.00');
        $item2->setCostPrice('3.50');
        $item2->setSortOrder(1);
        $wo1->addItem($item2);

        $item3 = new WorkOrderItem();
        $item3->setType(WorkOrderItemType::Labor);
        $item3->setName('Brake inspection and pad replacement');
        $item3->setQuantity('1.00');
        $item3->setUnitPrice('60.00');
        $item3->setSortOrder(2);
        $wo1->addItem($item3);

        $item4 = new WorkOrderItem();
        $item4->setType(WorkOrderItemType::Part);
        $item4->setName('Front Brake Pad Set');
        $item4->setPart($this->getReference(PartFixtures::PART_BRAKE_PAD, Part::class));
        $item4->setQuantity('1.00');
        $item4->setUnitPrice('55.00');
        $item4->setCostPrice('25.00');
        $item4->setSortOrder(3);
        $wo1->addItem($item4);

        $this->calculator->recalculate($wo1);
        $manager->persist($wo1);

        // Work order 2: Alice's X5 - spark plug replacement (completed).
        // Mileage left blank on purpose so fixtures cover both the
        // "recorded" and "not recorded" paths for tests / display.
        $wo2 = new WorkOrder();
        $wo2->setNumber('WO-2026-0002');
        $wo2->setClient($this->getReference(ClientFixtures::CLIENT_ALICE, Client::class));
        $wo2->setCar($this->getReference(CarFixtures::CAR_ALICE_X5, Car::class));
        $wo2->setStatus(WorkOrderStatus::Completed);
        $wo2->setComplaint('Engine misfiring');
        $wo2->setDiagnosis('Spark plugs fouled, replacement needed');
        $wo2->setStartedAt(new \DateTimeImmutable('-3 days'));
        $wo2->setCompletedAt(new \DateTimeImmutable('-2 days'));
        $wo2->setCreatedBy($admin);

        $item5 = new WorkOrderItem();
        $item5->setType(WorkOrderItemType::Labor);
        $item5->setName('Spark plug replacement');
        $item5->setQuantity('1.00');
        $item5->setUnitPrice('45.00');
        $item5->setSortOrder(0);
        $wo2->addItem($item5);

        $item6 = new WorkOrderItem();
        $item6->setType(WorkOrderItemType::Part);
        $item6->setName('Spark Plug');
        $item6->setPart($this->getReference(PartFixtures::PART_SPARK_PLUG, Part::class));
        $item6->setQuantity('4.00');
        $item6->setUnitPrice('9.50');
        $item6->setCostPrice('4.00');
        $item6->setSortOrder(1);
        $wo2->addItem($item6);

        // Free-form part line: shop bought 4 L of oil for the job and
        // resold it without picking from the catalog.
        $item7 = new WorkOrderItem();
        $item7->setType(WorkOrderItemType::Part);
        $item7->setName('Engine Oil 5W-30 (4L)');
        $item7->setQuantity('4.00');
        $item7->setUnitPrice('7.50');
        $item7->setCostPrice('4.20');
        $item7->setSortOrder(2);
        $wo2->addItem($item7);

        $this->calculator->recalculate($wo2);
        $manager->persist($wo2);

        // Two earlier completed work orders for John's Corolla so the
        // car details page shows a realistic mileage progression and a
        // non-empty service history.
        $history = [
            [
                'number' => 'WO-2025-0091',
                'openedDaysAgo' => 240,
                'startedDaysAgo' => 240,
                'completedDaysAgo' => 239,
                'odometer' => 105000,
                'complaint' => 'Routine service',
                'diagnosis' => 'Standard service interval reached',
                'item' => ['Routine service', '120.00'],
            ],
            [
                'number' => 'WO-2025-0142',
                'openedDaysAgo' => 120,
                'startedDaysAgo' => 120,
                'completedDaysAgo' => 119,
                'odometer' => 112500,
                'complaint' => 'Brake fluid replacement',
                'diagnosis' => 'Replaced brake fluid as preventive maintenance',
                'item' => ['Brake fluid replacement', '80.00'],
            ],
        ];

        foreach ($history as $entry) {
            $wo = new WorkOrder();
            $wo->setNumber($entry['number']);
            $wo->setClient($this->getReference(ClientFixtures::CLIENT_JOHN, Client::class));
            $wo->setCar($this->getReference(CarFixtures::CAR_JOHN_COROLLA, Car::class));
            $wo->setStatus(WorkOrderStatus::Completed);
            $wo->setComplaint($entry['complaint']);
            $wo->setDiagnosis($entry['diagnosis']);
            $wo->setOpenedAt(new \DateTimeImmutable('-' . $entry['openedDaysAgo'] . ' days'));
            $wo->setStartedAt(new \DateTimeImmutable('-' . $entry['startedDaysAgo'] . ' days'));
            $wo->setCompletedAt(new \DateTimeImmutable('-' . $entry['completedDaysAgo'] . ' days'));
            $wo->setCreatedBy($mechanic);
            $wo->setOdometerReading($entry['odometer']);
            $wo->setOdometerUnit(MileageUnit::Kilometres);

            $line = new WorkOrderItem();
            $line->setType(WorkOrderItemType::Labor);
            $line->setName($entry['item'][0]);
            $line->setQuantity('1.00');
            $line->setUnitPrice($entry['item'][1]);
            $line->setSortOrder(0);
            $wo->addItem($line);

            $this->calculator->recalculate($wo);
            $manager->persist($wo);
        }

        $manager->flush();

        $this->addReference(self::WO_JOHN_COROLLA, $wo1);
        $this->addReference(self::WO_ALICE_X5, $wo2);
    }

    public function getDependencies(): array
    {
        return [
            UserFixtures::class,
            ClientFixtures::class,
            CarFixtures::class,
            PartFixtures::class,
        ];
    }
}
