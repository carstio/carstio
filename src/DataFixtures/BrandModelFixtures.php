<?php

namespace App\DataFixtures;

use App\Entity\Car\Brand;
use App\Entity\Car\Model;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

/**
 * Realistic catalogue of car brands and models for a workshop demo.
 *
 * The data is hand-curated rather than imported at runtime: that way the
 * fixture is fully deterministic, works offline, and stays small enough to
 * code-review. Coverage focuses on what an independent European garage
 * realistically sees on the lift - passenger cars, vans, and a handful of
 * common LCV / truck brands. Sport / exotic / regional-only brands are
 * intentionally left out to keep the picker usable.
 *
 * To add or correct an entry, edit the {@see brands()} map below. Brands
 * are alphabetised, and models inside each brand are alphabetised as well.
 *
 * The reference constants are kept narrow on purpose: only the ones other
 * fixtures (CarFixtures) actually consume are exposed via addReference().
 * Adding a new model to the map does not require a new constant.
 */
class BrandModelFixtures extends Fixture
{
    public const BRAND_TOYOTA = 'brand-toyota';
    public const BRAND_BMW = 'brand-bmw';
    public const BRAND_FORD = 'brand-ford';
    public const BRAND_VOLKSWAGEN = 'brand-volkswagen';

    public const MODEL_COROLLA = 'model-corolla';
    public const MODEL_3_SERIES = 'model-3-series';
    public const MODEL_X5 = 'model-x5';
    public const MODEL_FOCUS = 'model-focus';

    public function load(ObjectManager $manager): void
    {
        $referencedBrands = [
            'Toyota' => self::BRAND_TOYOTA,
            'BMW' => self::BRAND_BMW,
            'Ford' => self::BRAND_FORD,
            'Volkswagen' => self::BRAND_VOLKSWAGEN,
        ];

        $referencedModels = [
            'Toyota|Corolla' => self::MODEL_COROLLA,
            'BMW|3 Series' => self::MODEL_3_SERIES,
            'BMW|X5' => self::MODEL_X5,
            'Ford|Focus' => self::MODEL_FOCUS,
        ];

        foreach (self::brands() as $brandName => $modelNames) {
            $brand = new Brand();
            $brand->setName($brandName);
            $manager->persist($brand);

            if (isset($referencedBrands[$brandName])) {
                $this->addReference($referencedBrands[$brandName], $brand);
            }

            foreach ($modelNames as $modelName) {
                $model = new Model();
                $model->setName($modelName);
                $model->setBrand($brand);
                $manager->persist($model);

                $key = $brandName . '|' . $modelName;
                if (isset($referencedModels[$key])) {
                    $this->addReference($referencedModels[$key], $model);
                }
            }
        }

        $manager->flush();
    }

    /**
     * Brand => list of models. Public so tests can verify the dataset
     * without booting the kernel or hitting the database.
     *
     * @return array<string, list<string>>
     */
    public static function brands(): array
    {
        return [
            'Alfa Romeo' => [
                '147', '156', '159', '166', 'Brera', 'Giulia', 'Giulietta',
                'GT', 'MiTo', 'Spider', 'Stelvio', 'Tonale',
            ],
            'Audi' => [
                'A1', 'A3', 'A4', 'A5', 'A6', 'A7', 'A8',
                'e-tron', 'Q2', 'Q3', 'Q4 e-tron', 'Q5', 'Q7', 'Q8',
                'RS3', 'RS4', 'RS6', 'S3', 'S4', 'S5',
                'TT',
            ],
            'BMW' => [
                '1 Series', '2 Series', '3 Series', '4 Series', '5 Series',
                '6 Series', '7 Series', '8 Series',
                'i3', 'i4', 'i5', 'i7', 'iX', 'iX3',
                'M2', 'M3', 'M4', 'M5', 'M8',
                'X1', 'X2', 'X3', 'X4', 'X5', 'X6', 'X7',
                'Z4',
            ],
            'Chevrolet' => [
                'Aveo', 'Camaro', 'Captiva', 'Corvette', 'Cruze',
                'Lacetti', 'Malibu', 'Matiz', 'Orlando', 'Spark',
                'Tahoe', 'Trax',
            ],
            'Chrysler' => [
                '300C', 'Grand Voyager', 'PT Cruiser', 'Sebring', 'Voyager',
            ],
            'Citroën' => [
                'Berlingo', 'C1', 'C2', 'C3', 'C3 Aircross', 'C3 Picasso',
                'C4', 'C4 Cactus', 'C4 Picasso', 'C4 SpaceTourer',
                'C5', 'C5 Aircross', 'C5 X', 'C6', 'C8',
                'DS3', 'DS4', 'DS5',
                'Jumper', 'Jumpy', 'Nemo', 'SpaceTourer', 'Xsara', 'Xsara Picasso',
            ],
            'Cupra' => [
                'Ateca', 'Born', 'Formentor', 'Leon', 'Tavascan', 'Terramar',
            ],
            'Dacia' => [
                'Dokker', 'Duster', 'Jogger', 'Lodgy', 'Logan',
                'Sandero', 'Sandero Stepway', 'Spring',
            ],
            'Dodge' => [
                'Caliber', 'Challenger', 'Charger', 'Durango', 'Journey', 'Nitro', 'RAM',
            ],
            'Fiat' => [
                '500', '500C', '500e', '500L', '500X', '600',
                'Bravo', 'Croma', 'Doblò', 'Ducato', 'Fiorino',
                'Freemont', 'Grande Punto', 'Idea', 'Linea',
                'Multipla', 'Panda', 'Punto', 'Qubo',
                'Scudo', 'Sedici', 'Stilo', 'Talento', 'Tipo',
            ],
            'Ford' => [
                'B-Max', 'C-Max', 'EcoSport', 'Edge', 'Explorer',
                'Fiesta', 'Focus', 'Fusion', 'Galaxy',
                'Ka', 'Ka+', 'Kuga', 'Mondeo', 'Mustang', 'Mustang Mach-E',
                'Puma', 'Ranger', 'S-Max', 'Tourneo Connect', 'Tourneo Courier',
                'Tourneo Custom', 'Transit', 'Transit Connect', 'Transit Courier', 'Transit Custom',
            ],
            'Honda' => [
                'Accord', 'City', 'Civic', 'CR-V', 'CR-Z',
                'e', 'FR-V', 'HR-V', 'Insight', 'Jazz',
                'Legend', 'Prelude', 'S2000', 'Stream', 'ZR-V',
            ],
            'Hyundai' => [
                'Bayon', 'Elantra', 'Galloper', 'Genesis', 'Getz',
                'i10', 'i20', 'i30', 'i40', 'Ioniq', 'Ioniq 5', 'Ioniq 6',
                'ix20', 'ix35', 'ix55', 'Kona', 'Matrix',
                'Nexo', 'Santa Fe', 'Sonata', 'Staria', 'Terracan', 'Tucson',
            ],
            'Iveco' => [
                'Daily', 'EuroCargo', 'S-Way', 'Stralis', 'Trakker',
            ],
            'Jaguar' => [
                'E-Pace', 'F-Pace', 'F-Type', 'I-Pace', 'S-Type',
                'X-Type', 'XE', 'XF', 'XJ', 'XK',
            ],
            'Jeep' => [
                'Avenger', 'Cherokee', 'Commander', 'Compass',
                'Grand Cherokee', 'Patriot', 'Renegade', 'Wrangler',
            ],
            'Kia' => [
                'Carens', 'Carnival', 'Ceed', 'Cerato', 'EV6', 'EV9',
                'Magentis', 'Niro', 'Optima', 'Picanto', 'ProCeed',
                'Rio', 'Sorento', 'Soul', 'Sportage', 'Stinger',
                'Stonic', 'Venga', 'XCeed',
            ],
            'Land Rover' => [
                'Defender', 'Discovery', 'Discovery Sport', 'Freelander',
                'Range Rover', 'Range Rover Evoque', 'Range Rover Sport', 'Range Rover Velar',
            ],
            'Lexus' => [
                'CT', 'ES', 'GS', 'IS', 'LBX', 'LC', 'LS',
                'NX', 'RC', 'RX', 'RZ', 'UX',
            ],
            'MAN' => [
                'TGE', 'TGL', 'TGM', 'TGS', 'TGX',
            ],
            'Mazda' => [
                '2', '3', '5', '6', 'BT-50',
                'CX-3', 'CX-30', 'CX-5', 'CX-60', 'CX-7', 'CX-9',
                'MX-30', 'MX-5', 'RX-8',
            ],
            'Mercedes-Benz' => [
                'A-Class', 'B-Class', 'C-Class', 'CLA', 'CLE', 'CLK', 'CLS',
                'E-Class', 'EQA', 'EQB', 'EQC', 'EQE', 'EQS', 'EQV',
                'G-Class', 'GL', 'GLA', 'GLB', 'GLC', 'GLE', 'GLK', 'GLS',
                'ML', 'S-Class', 'SL', 'SLC', 'SLK',
                'Citan', 'Sprinter', 'V-Class', 'Viano', 'Vito', 'X-Class',
            ],
            'Mini' => [
                'Cabrio', 'Clubman', 'Cooper', 'Cooper SE', 'Countryman',
                'Hatch', 'Paceman', 'Roadster',
            ],
            'Mitsubishi' => [
                'ASX', 'Colt', 'Eclipse Cross', 'Galant', 'Grandis',
                'L200', 'Lancer', 'Outlander', 'Pajero', 'Pajero Sport',
                'Space Star',
            ],
            'Nissan' => [
                '350Z', '370Z', 'Almera', 'Ariya', 'Cube',
                'GT-R', 'Juke', 'Leaf', 'Micra', 'Murano',
                'Navara', 'Note', 'NV200', 'NV300', 'NV400',
                'Pathfinder', 'Patrol', 'Pixo', 'Primera', 'Pulsar',
                'Qashqai', 'Qashqai+2', 'Townstar', 'X-Trail',
            ],
            'Opel' => [
                'Adam', 'Agila', 'Antara', 'Astra', 'Cascada',
                'Combo', 'Corsa', 'Crossland', 'Frontera',
                'Grandland', 'Insignia', 'Karl', 'Meriva',
                'Mokka', 'Movano', 'Omega', 'Signum', 'Tigra',
                'Vectra', 'Vivaro', 'Zafira',
            ],
            'Peugeot' => [
                '106', '107', '108', '206', '207', '208', '301', '307', '308',
                '407', '408', '508', '607', '807',
                '2008', '3008', '4007', '4008', '5008',
                'Bipper', 'Boxer', 'Expert', 'iOn', 'Partner',
                'Rifter', 'RCZ', 'Traveller',
            ],
            'Porsche' => [
                '718 Boxster', '718 Cayman', '911', 'Cayenne',
                'Macan', 'Panamera', 'Taycan',
            ],
            'Renault' => [
                'Captur', 'Clio', 'Espace', 'Express',
                'Fluence', 'Grand Scenic', 'Kadjar', 'Kangoo', 'Koleos',
                'Laguna', 'Latitude', 'Master', 'Megane', 'Megane E-Tech',
                'Modus', 'Scenic', 'Talisman', 'Trafic',
                'Twingo', 'Twizy', 'Wind', 'Zoe',
            ],
            'Seat' => [
                'Alhambra', 'Altea', 'Arona', 'Arosa', 'Ateca',
                'Cordoba', 'Exeo', 'Ibiza', 'Leon', 'Mii',
                'Tarraco', 'Toledo',
            ],
            'Škoda' => [
                'Citigo', 'Enyaq', 'Fabia', 'Favorit', 'Felicia',
                'Kamiq', 'Karoq', 'Kodiaq', 'Octavia',
                'Rapid', 'Roomster', 'Scala', 'Superb', 'Yeti',
            ],
            'Smart' => [
                'EQ ForFour', 'EQ ForTwo', 'ForFour', 'ForTwo', 'Roadster',
            ],
            'Subaru' => [
                'BRZ', 'Forester', 'Impreza', 'Justy', 'Legacy',
                'Levorg', 'Outback', 'Solterra', 'Tribeca', 'WRX', 'XV',
            ],
            'Suzuki' => [
                'Across', 'Alto', 'Baleno', 'Celerio', 'Grand Vitara',
                'Ignis', 'Jimny', 'Kizashi', 'Liana', 'Splash',
                'Swace', 'Swift', 'SX4', 'SX4 S-Cross', 'Vitara', 'Wagon R+',
            ],
            'Tesla' => [
                'Cybertruck', 'Model 3', 'Model S', 'Model X', 'Model Y', 'Roadster',
            ],
            'Toyota' => [
                'Auris', 'Avensis', 'Aygo', 'Aygo X', 'bZ4X',
                'Camry', 'Carina', 'C-HR', 'Corolla', 'Corolla Cross',
                'Corolla Verso', 'GR86', 'GR Yaris', 'Highlander',
                'Hilux', 'iQ', 'Land Cruiser', 'Mirai',
                'Prius', 'Prius+', 'Proace', 'Proace City', 'Proace Verso',
                'RAV4', 'Supra', 'Urban Cruiser', 'Verso', 'Yaris', 'Yaris Cross',
            ],
            'Volkswagen' => [
                'Amarok', 'Arteon', 'Beetle', 'Caddy', 'California',
                'Caravelle', 'CC', 'Crafter', 'Eos', 'Fox',
                'Golf', 'Golf Plus', 'Golf Sportsvan',
                'ID.3', 'ID.4', 'ID.5', 'ID.7', 'ID. Buzz',
                'Jetta', 'Lupo', 'Multivan', 'New Beetle',
                'Passat', 'Phaeton', 'Polo', 'Scirocco', 'Sharan',
                'T-Cross', 'T-Roc', 'Taigo', 'Tiguan', 'Tiguan Allspace',
                'Touareg', 'Touran', 'Transporter', 'up!',
            ],
            'Volvo' => [
                'C30', 'C40 Recharge', 'C70',
                'EX30', 'EX90', 'S40', 'S60', 'S80', 'S90',
                'V40', 'V50', 'V60', 'V70', 'V90',
                'XC40', 'XC60', 'XC70', 'XC90',
            ],
        ];
    }
}
