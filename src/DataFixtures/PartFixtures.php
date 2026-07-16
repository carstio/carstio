<?php

namespace App\DataFixtures;

use App\Entity\Part\Part;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

class PartFixtures extends Fixture
{
    public const PART_OIL_FILTER = 'part-oil-filter';
    public const PART_BRAKE_PAD = 'part-brake-pad';
    public const PART_SPARK_PLUG = 'part-spark-plug';
    public const PART_AIR_FILTER = 'part-air-filter';
    public const PART_ENGINE_OIL = 'part-engine-oil';

    public function load(ObjectManager $manager): void
    {
        $oilFilter = new Part();
        $oilFilter->setName('Oil Filter');
        $oilFilter->setSku('OF-001');
        $oilFilter->setPartNumber('90915-YZZD1');
        $oilFilter->setBrand('Toyota');
        $oilFilter->setCostPrice('3.50');
        $oilFilter->setSellPrice('8.00');
        $manager->persist($oilFilter);

        $brakePad = new Part();
        $brakePad->setName('Front Brake Pad Set');
        $brakePad->setSku('BP-002');
        $brakePad->setPartNumber('04465-02220');
        $brakePad->setBrand('Toyota');
        $brakePad->setCostPrice('25.00');
        $brakePad->setSellPrice('55.00');
        $manager->persist($brakePad);

        $sparkPlug = new Part();
        $sparkPlug->setName('Spark Plug');
        $sparkPlug->setSku('SP-003');
        $sparkPlug->setPartNumber('90919-01253');
        $sparkPlug->setBrand('NGK');
        $sparkPlug->setCostPrice('4.00');
        $sparkPlug->setSellPrice('9.50');
        $manager->persist($sparkPlug);

        // Free-form catalog entry: realistic for shops that buy/resell a
        // common part but never bothered to log a SKU.
        $airFilter = new Part();
        $airFilter->setName('Air Filter');
        $airFilter->setBrand('Toyota');
        $airFilter->setCostPrice('5.00');
        $airFilter->setSellPrice('12.00');
        $manager->persist($airFilter);

        // Catalog entry without prices yet - shop will fill them in when
        // ordering. Demonstrates that prices are optional.
        $engineOil = new Part();
        $engineOil->setName('Engine Oil 5W-30');
        $engineOil->setBrand('Castrol');
        $manager->persist($engineOil);

        $manager->flush();

        $this->addReference(self::PART_OIL_FILTER, $oilFilter);
        $this->addReference(self::PART_BRAKE_PAD, $brakePad);
        $this->addReference(self::PART_SPARK_PLUG, $sparkPlug);
        $this->addReference(self::PART_AIR_FILTER, $airFilter);
        $this->addReference(self::PART_ENGINE_OIL, $engineOil);
    }
}
