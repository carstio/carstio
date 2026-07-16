<?php

namespace App\DataFixtures;

use App\Entity\Car\Car;
use App\Entity\Client\Client;
use App\Entity\Part\Part;
use App\Entity\Sales\Offer;
use App\Entity\Sales\OfferItem;
use App\Entity\User;
use App\Enum\OfferItemType;
use App\Enum\OfferStatus;
use App\Service\OfferCalculator;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;

class OfferFixtures extends Fixture implements DependentFixtureInterface
{
    public const OFFER_DRAFT = 'offer-bob-focus-draft';
    public const OFFER_SENT = 'offer-alice-x5-sent';
    public const OFFER_APPROVED = 'offer-john-3series-approved';
    public const OFFER_REJECTED = 'offer-john-corolla-rejected';

    public function __construct(
        private readonly OfferCalculator $calculator,
    ) {
    }

    public function load(ObjectManager $manager): void
    {
        $admin = $this->getReference(UserFixtures::ADMIN_REFERENCE, User::class);
        $mechanic = $this->getReference(UserFixtures::MECHANIC_REFERENCE, User::class);

        // Offer 1: Draft - Bob's Focus - timing belt kit quotation
        $offer1 = new Offer();
        $offer1->setNumber('OF-2026-0001');
        $offer1->setClient($this->getReference(ClientFixtures::CLIENT_BOB, Client::class));
        $offer1->setCar($this->getReference(CarFixtures::CAR_BOB_FOCUS, Car::class));
        $offer1->setCreatedBy($mechanic);
        $offer1->setStatus(OfferStatus::Draft);
        $offer1->setTitle('Timing belt replacement');
        $offer1->setNotes('Quote prepared after visual inspection.');
        $offer1->setValidUntil(new \DateTimeImmutable('+14 days'));

        $item = new OfferItem();
        $item->setType(OfferItemType::Labor);
        $item->setName('Timing belt replacement labor');
        $item->setQuantity('3.00');
        $item->setUnitPrice('40.00');
        $item->setSortOrder(0);
        $offer1->addItem($item);

        $item = new OfferItem();
        $item->setType(OfferItemType::Part);
        $item->setName('Spark Plug');
        $item->setPart($this->getReference(PartFixtures::PART_SPARK_PLUG, Part::class));
        $item->setQuantity('4.00');
        $item->setUnitPrice('9.50');
        $item->setCostPrice('4.00');
        $item->setSortOrder(1);
        $offer1->addItem($item);

        $this->calculator->recalculate($offer1);
        $manager->persist($offer1);

        // Offer 2: Sent - Alice's X5 - brake service quotation
        $offer2 = new Offer();
        $offer2->setNumber('OF-2026-0002');
        $offer2->setClient($this->getReference(ClientFixtures::CLIENT_ALICE, Client::class));
        $offer2->setCar($this->getReference(CarFixtures::CAR_ALICE_X5, Car::class));
        $offer2->setCreatedBy($admin);
        $offer2->setStatus(OfferStatus::Sent);
        $offer2->setTitle('Front brake service');
        $offer2->setValidUntil(new \DateTimeImmutable('+10 days'));

        $item = new OfferItem();
        $item->setType(OfferItemType::Labor);
        $item->setName('Brake pad replacement');
        $item->setQuantity('1.50');
        $item->setUnitPrice('60.00');
        $item->setSortOrder(0);
        $offer2->addItem($item);

        $item = new OfferItem();
        $item->setType(OfferItemType::Part);
        $item->setName('Front Brake Pad Set');
        $item->setPart($this->getReference(PartFixtures::PART_BRAKE_PAD, Part::class));
        $item->setQuantity('1.00');
        $item->setUnitPrice('55.00');
        $item->setCostPrice('25.00');
        $item->setSortOrder(1);
        $offer2->addItem($item);

        $this->calculator->recalculate($offer2);
        $manager->persist($offer2);

        // Offer 3: Approved (convertible!) - John's 3 Series - full service
        $offer3 = new Offer();
        $offer3->setNumber('OF-2026-0003');
        $offer3->setClient($this->getReference(ClientFixtures::CLIENT_JOHN, Client::class));
        $offer3->setCar($this->getReference(CarFixtures::CAR_JOHN_3SERIES, Car::class));
        $offer3->setCreatedBy($mechanic);
        $offer3->setStatus(OfferStatus::Approved);
        $offer3->setTitle('Full service');
        $offer3->setNotes('Customer approved by email.');
        $offer3->setValidUntil(new \DateTimeImmutable('+21 days'));

        $item = new OfferItem();
        $item->setType(OfferItemType::Labor);
        $item->setName('Full service labor');
        $item->setQuantity('2.50');
        $item->setUnitPrice('50.00');
        $item->setSortOrder(0);
        $offer3->addItem($item);

        $item = new OfferItem();
        $item->setType(OfferItemType::Part);
        $item->setName('Oil Filter');
        $item->setPart($this->getReference(PartFixtures::PART_OIL_FILTER, Part::class));
        $item->setQuantity('1.00');
        $item->setUnitPrice('8.00');
        $item->setCostPrice('3.50');
        $item->setSortOrder(1);
        $offer3->addItem($item);

        $item = new OfferItem();
        $item->setType(OfferItemType::Misc);
        $item->setName('Disposal fee');
        $item->setQuantity('1.00');
        $item->setUnitPrice('5.00');
        $item->setSortOrder(2);
        $offer3->addItem($item);

        $this->calculator->recalculate($offer3);
        $manager->persist($offer3);

        // Offer 4: Rejected - John's Corolla - suspension rebuild (too expensive)
        $offer4 = new Offer();
        $offer4->setNumber('OF-2026-0004');
        $offer4->setClient($this->getReference(ClientFixtures::CLIENT_JOHN, Client::class));
        $offer4->setCar($this->getReference(CarFixtures::CAR_JOHN_COROLLA, Car::class));
        $offer4->setCreatedBy($admin);
        $offer4->setStatus(OfferStatus::Rejected);
        $offer4->setTitle('Suspension rebuild');
        $offer4->setNotes('Declined - customer opted to postpone.');

        $item = new OfferItem();
        $item->setType(OfferItemType::Labor);
        $item->setName('Full suspension rebuild');
        $item->setQuantity('6.00');
        $item->setUnitPrice('55.00');
        $item->setSortOrder(0);
        $offer4->addItem($item);

        $this->calculator->recalculate($offer4);
        $manager->persist($offer4);

        // Offer 5: Converted - Bob's Focus - small inspection, already turned
        // into a work order. Gives the offer-conversion KPI a non-zero
        // numerator without affecting existing tests.
        $offer5 = new Offer();
        $offer5->setNumber('OF-2026-0005');
        $offer5->setClient($this->getReference(ClientFixtures::CLIENT_BOB, Client::class));
        $offer5->setCar($this->getReference(CarFixtures::CAR_BOB_FOCUS, Car::class));
        $offer5->setCreatedBy($admin);
        $offer5->setStatus(OfferStatus::Converted);
        $offer5->setTitle('Pre-purchase inspection');
        $offer5->setNotes('Converted to work order after customer approval.');
        $offer5->setConvertedAt(new \DateTimeImmutable('-1 day'));

        $item = new OfferItem();
        $item->setType(OfferItemType::Labor);
        $item->setName('Inspection');
        $item->setQuantity('1.00');
        $item->setUnitPrice('40.00');
        $item->setSortOrder(0);
        $offer5->addItem($item);

        $this->calculator->recalculate($offer5);
        $manager->persist($offer5);

        $manager->flush();

        $this->addReference(self::OFFER_DRAFT, $offer1);
        $this->addReference(self::OFFER_SENT, $offer2);
        $this->addReference(self::OFFER_APPROVED, $offer3);
        $this->addReference(self::OFFER_REJECTED, $offer4);
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
