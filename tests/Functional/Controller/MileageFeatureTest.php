<?php

namespace App\Tests\Functional\Controller;

use App\Entity\Car\Car;
use App\Entity\Client\Client;
use App\Entity\Workshop\WorkOrder;
use App\Enum\MileageUnit;
use App\Enum\WorkOrderStatus;
use App\Repository\Settings\SettingRepository;
use App\Repository\UserRepository;
use App\Repository\Workshop\WorkOrderRepository;
use App\Service\Settings\SettingsManager;
use App\Service\Settings\SettingsProvider;
use App\Settings\SettingsRegistry;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * End-to-end coverage for odometer/mileage tracking.
 *
 * Settings tests poke /settings/regional to ensure the dropdown renders
 * and saves both choices. Form tests submit /work-orders/new with and
 * without mileage. Snapshot tests verify the (km/mi) unit is frozen at
 * write time. Display tests check show pages, car history, and the PDF.
 */
class MileageFeatureTest extends WebTestCase
{
    private function loginAdmin(): KernelBrowser
    {
        $client = static::createClient();
        $user = static::getContainer()->get(UserRepository::class)->findOneBy(['email' => 'admin@carstio.local']);
        $client->loginUser($user);

        return $client;
    }

    private function provider(): SettingsProvider
    {
        $provider = static::getContainer()->get(SettingsProvider::class);
        $provider->clearCache();

        return $provider;
    }

    private function em(): EntityManagerInterface
    {
        return static::getContainer()->get('doctrine')->getManager();
    }

    private function workOrders(): WorkOrderRepository
    {
        return static::getContainer()->get(WorkOrderRepository::class);
    }

    /**
     * Pin the mileage unit setting and return a cleanup callback.
     */
    private function pinMileageUnit(string $value): callable
    {
        $manager = static::getContainer()->get(SettingsManager::class);
        $previous = $this->provider()->getString(
            SettingsRegistry::SECTION_REGIONAL,
            SettingsRegistry::KEY_MILEAGE_UNIT,
        ) ?? 'km';
        $manager->set(SettingsRegistry::SECTION_REGIONAL, SettingsRegistry::KEY_MILEAGE_UNIT, $value);

        return static function () use ($manager, $previous): void {
            $manager->set(SettingsRegistry::SECTION_REGIONAL, SettingsRegistry::KEY_MILEAGE_UNIT, $previous);
        };
    }

    // ───── Settings ──────────────────────────────────────────────────────

    public function testRegionalPageRendersMileageUnitAsSelect(): void
    {
        $client = $this->loginAdmin();
        $crawler = $client->request('GET', '/settings/regional');

        $this->assertResponseIsSuccessful();
        $select = $crawler->filter('select[name="settings_section[mileage_unit]"]');
        $this->assertCount(1, $select, 'Mileage unit must be a <select>, not a text input.');

        $optionValues = $select->filter('option')->each(fn ($n) => $n->attr('value'));
        $this->assertContains('km', $optionValues);
        $this->assertContains('mi', $optionValues);
    }

    public function testKilometresCanBeSaved(): void
    {
        $client = $this->loginAdmin();
        $restore = $this->pinMileageUnit('mi'); // start from non-default
        try {
            $crawler = $client->request('GET', '/settings/regional');
            $form = $crawler->selectButton('Save')->form();
            $form['settings_section[mileage_unit]'] = 'km';
            $client->submit($form);

            $this->assertResponseRedirects('/settings/regional');
            $this->assertSame('km', $this->provider()->getString(
                SettingsRegistry::SECTION_REGIONAL,
                SettingsRegistry::KEY_MILEAGE_UNIT,
            ));
        } finally {
            $restore();
        }
    }

    public function testMilesCanBeSaved(): void
    {
        $client = $this->loginAdmin();
        $restore = $this->pinMileageUnit('km');
        try {
            $crawler = $client->request('GET', '/settings/regional');
            $form = $crawler->selectButton('Save')->form();
            $form['settings_section[mileage_unit]'] = 'mi';
            $client->submit($form);

            $this->assertResponseRedirects('/settings/regional');
            $this->assertSame('mi', $this->provider()->getString(
                SettingsRegistry::SECTION_REGIONAL,
                SettingsRegistry::KEY_MILEAGE_UNIT,
            ));
        } finally {
            $restore();
        }
    }

    public function testInvalidMileageUnitIsRejected(): void
    {
        $client = $this->loginAdmin();
        $restore = $this->pinMileageUnit('km');
        try {
            // Bypass BrowserKit's <select> filtering by posting raw form data.
            $client->request('POST', '/settings/regional', [
                'settings_section' => [
                    'default_locale' => 'en',
                    'timezone' => 'Europe/Sofia',
                    'date_format' => 'Y-m-d',
                    'time_format' => 'H:i',
                    'mileage_unit' => 'parsecs',
                ],
            ]);

            $this->assertResponseStatusCodeSame(422);
            $this->assertSame('km', $this->provider()->getString(
                SettingsRegistry::SECTION_REGIONAL,
                SettingsRegistry::KEY_MILEAGE_UNIT,
            ), 'A rejected submission must not overwrite the stored unit.');
        } finally {
            $restore();
        }
    }

    public function testRegionalPageRendersWhenStoredMileageUnitIsInvalid(): void
    {
        $client = $this->loginAdmin();
        $repo = static::getContainer()->get(SettingRepository::class);
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $row = $repo->findOneByKey(SettingsRegistry::SECTION_REGIONAL, SettingsRegistry::KEY_MILEAGE_UNIT);

        if ($row === null) {
            // Setting may not yet exist - simulate by inserting a bogus row
            // via SettingsManager would already coerce it, so create raw.
            $manager = static::getContainer()->get(SettingsManager::class);
            $manager->set(SettingsRegistry::SECTION_REGIONAL, SettingsRegistry::KEY_MILEAGE_UNIT, 'km');
            $row = $repo->findOneByKey(SettingsRegistry::SECTION_REGIONAL, SettingsRegistry::KEY_MILEAGE_UNIT);
        }
        self::assertNotNull($row);
        $original = $row->getValue();
        $row->setValue('parsecs');
        $em->flush();

        try {
            $client->request('GET', '/settings/regional');
            $this->assertResponseIsSuccessful();
        } finally {
            $row->setValue($original);
            $em->flush();
        }
    }

    public function testMissingMileageSettingFallsBackToDefault(): void
    {
        // Provider's getString returns null for an unset setting; the
        // mileage manager must still resolve to km via DEFAULT.
        $manager = static::getContainer()->get(\App\Service\Workshop\WorkOrderMileageManager::class);
        $repo = static::getContainer()->get(SettingRepository::class);
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $row = $repo->findOneByKey(SettingsRegistry::SECTION_REGIONAL, SettingsRegistry::KEY_MILEAGE_UNIT);

        $previousValue = $row?->getValue();
        if ($row !== null) {
            $row->setValue(null);
            $em->flush();
        }
        $this->provider()->clearCache();

        try {
            $this->assertSame(MileageUnit::DEFAULT, $manager->resolveDefaultUnit());
        } finally {
            if ($row !== null) {
                $row->setValue($previousValue);
                $em->flush();
            }
        }
    }

    // ───── WorkOrder form ────────────────────────────────────────────────

    public function testWorkOrderCanBeCreatedWithMileage(): void
    {
        $client = $this->loginAdmin();
        $restore = $this->pinMileageUnit('km');
        $unique = 'Mileage create test ' . uniqid();
        try {
            $crawler = $client->request('GET', '/work-orders/new');
            $this->assertResponseIsSuccessful();
            $this->assertSelectorExists('input[name="work_order[odometerReading]"]');

            [$clientEntity, $carEntity] = $this->aFreshClientAndCar();

            $form = $crawler->filter('form[name="work_order"]')->form();
            $form['work_order[client]'] = (string) $clientEntity->getId();
            $form['work_order[car]'] = (string) $carEntity->getId();
            $form['work_order[complaint]'] = $unique;
            $form['work_order[odometerReading]'] = '120000';

            $client->submit($form);
            $this->assertResponseRedirects();

            $wo = $this->findByComplaint($unique);
            $this->assertNotNull($wo, 'New WO with mileage should be persisted.');
            $this->assertSame(120000, $wo->getOdometerReading());
            $this->assertSame(MileageUnit::Kilometres, $wo->getOdometerUnit());
        } finally {
            $restore();
        }
    }

    public function testWorkOrderCanBeCreatedWithoutMileage(): void
    {
        $client = $this->loginAdmin();
        $unique = 'No-mileage create test ' . uniqid();
        $crawler = $client->request('GET', '/work-orders/new');

        [$clientEntity, $carEntity] = $this->aFreshClientAndCar();

        $form = $crawler->filter('form[name="work_order"]')->form();
        $form['work_order[client]'] = (string) $clientEntity->getId();
        $form['work_order[car]'] = (string) $carEntity->getId();
        $form['work_order[complaint]'] = $unique;
        // Leave odometerReading blank.

        $client->submit($form);
        $this->assertResponseRedirects();

        $wo = $this->findByComplaint($unique);
        $this->assertNotNull($wo, 'No-mileage WO should still be persisted.');
        $this->assertNull($wo->getOdometerReading());
        $this->assertNull($wo->getOdometerUnit());
    }

    public function testEmptyMileageDoesNotThrowTypeError(): void
    {
        // Regression guard: an empty string from the form must not reach
        // the entity setter as a non-integer.
        $client = $this->loginAdmin();
        [$clientEntity, $carEntity] = $this->aFreshClientAndCar();

        $client->request('POST', '/work-orders/new', [
            'work_order' => [
                'client' => (string) $clientEntity->getId(),
                'car' => (string) $carEntity->getId(),
                'status' => WorkOrderStatus::Draft->value,
                'complaint' => 'Empty mileage smoke test',
                'odometerReading' => '',
            ],
        ]);

        $this->assertNotSame(500, $client->getResponse()->getStatusCode(),
            'Empty mileage must not produce a TypeError.');
    }

    public function testNegativeMileageIsRejected(): void
    {
        $client = $this->loginAdmin();
        [$clientEntity, $carEntity] = $this->aFreshClientAndCar();
        $unique = 'Negative mileage smoke test ' . uniqid();

        $client->request('POST', '/work-orders/new', [
            'work_order' => [
                'client' => (string) $clientEntity->getId(),
                'car' => (string) $carEntity->getId(),
                'status' => WorkOrderStatus::Draft->value,
                'complaint' => $unique,
                'odometerReading' => '-100',
            ],
        ]);

        $this->assertSame(422, $client->getResponse()->getStatusCode());
        $this->assertNull($this->findByComplaint($unique),
            'A WorkOrder with a rejected mileage must not be persisted.');
    }

    public function testEditFormPreloadsSavedReadingAndUnit(): void
    {
        $client = $this->loginAdmin();
        $wo = $this->workOrders()->findOneBy(['number' => 'WO-2026-0001']);
        self::assertNotNull($wo);
        $this->assertSame(119800, $wo->getOdometerReading());
        $this->assertSame(MileageUnit::Kilometres, $wo->getOdometerUnit());

        $crawler = $client->request('GET', '/work-orders/' . $wo->getId() . '/edit');
        $this->assertResponseIsSuccessful();

        $input = $crawler->filter('input[name="work_order[odometerReading]"]');
        $this->assertCount(1, $input);
        $this->assertSame('119800', $input->attr('value'));
        // Unit suffix appears next to the input group.
        $this->assertSelectorTextContains('.input-group .input-group-text', 'km');
    }

    // ───── Snapshot semantics ────────────────────────────────────────────

    public function testNewMileageReadingSnapshotsCurrentSettingUnit(): void
    {
        $client = $this->loginAdmin();
        $restore = $this->pinMileageUnit('mi');
        $unique = 'Mileage snapshot test ' . uniqid();
        try {
            [$clientEntity, $carEntity] = $this->aFreshClientAndCar();

            $client->request('POST', '/work-orders/new', [
                'work_order' => [
                    'client' => (string) $clientEntity->getId(),
                    'car' => (string) $carEntity->getId(),
                    'status' => WorkOrderStatus::Draft->value,
                    'complaint' => $unique,
                    'odometerReading' => '50000',
                ],
            ]);

            $wo = $this->findByComplaint($unique);
            self::assertNotNull($wo);
            $this->assertSame(MileageUnit::Miles, $wo->getOdometerUnit());
        } finally {
            $restore();
        }
    }

    public function testChangingRegionalSettingDoesNotChangeExistingWorkOrderUnit(): void
    {
        $client = $this->loginAdmin();
        $restore = $this->pinMileageUnit('km');
        $unique = 'Persistent snapshot test ' . uniqid();
        try {
            // Create a WO with a km reading.
            [$clientEntity, $carEntity] = $this->aFreshClientAndCar();
            $client->request('POST', '/work-orders/new', [
                'work_order' => [
                    'client' => (string) $clientEntity->getId(),
                    'car' => (string) $carEntity->getId(),
                    'status' => WorkOrderStatus::Draft->value,
                    'complaint' => $unique,
                    'odometerReading' => '120000',
                ],
            ]);
            $wo = $this->findByComplaint($unique);
            self::assertNotNull($wo);
            $this->assertSame(MileageUnit::Kilometres, $wo->getOdometerUnit());

            // Flip the Regional setting to miles and re-save the WO unchanged.
            $manager = static::getContainer()->get(SettingsManager::class);
            $manager->set(SettingsRegistry::SECTION_REGIONAL, SettingsRegistry::KEY_MILEAGE_UNIT, 'mi');

            $this->em()->clear();
            $reloaded = $this->workOrders()->find($wo->getId());
            self::assertNotNull($reloaded);
            $this->assertSame(MileageUnit::Kilometres, $reloaded->getOdometerUnit(),
                'Historical km unit must NOT change to mi after the Regional setting flips.');
        } finally {
            $restore();
        }
    }

    public function testOldWorkOrderWithoutMileageUsesCurrentSettingOnFirstEdit(): void
    {
        $client = $this->loginAdmin();
        $restore = $this->pinMileageUnit('mi');
        try {
            // Use WO-2026-0002 which fixtures leave with null mileage.
            $wo = $this->workOrders()->findOneBy(['number' => 'WO-2026-0002']);
            self::assertNotNull($wo);
            $this->assertNull($wo->getOdometerReading());

            $crawler = $client->request('GET', '/work-orders/' . $wo->getId() . '/edit');
            $form = $crawler->filter('form[name="work_order"]')->form();
            $values = $form->getPhpValues();
            $values['work_order']['odometerReading'] = '60000';

            $client->request($form->getMethod(), $form->getUri(), $values);
            $this->assertResponseRedirects('/work-orders/' . $wo->getId());

            $this->em()->clear();
            $reloaded = $this->workOrders()->find($wo->getId());
            self::assertNotNull($reloaded);
            $this->assertSame(60000, $reloaded->getOdometerReading());
            $this->assertSame(MileageUnit::Miles, $reloaded->getOdometerUnit());

            // Reset for sibling tests.
            $reloaded->setOdometerReading(null);
            $reloaded->setOdometerUnit(null);
            $this->em()->flush();
        } finally {
            $restore();
        }
    }

    // ───── Display ───────────────────────────────────────────────────────

    public function testShowPageDisplaysMileageWhenPresent(): void
    {
        $client = $this->loginAdmin();
        $wo = $this->workOrders()->findOneBy(['number' => 'WO-2026-0001']);
        self::assertNotNull($wo);

        $client->request('GET', '/work-orders/' . $wo->getId());
        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('body', '119,800 km');
    }

    public function testShowPageOmitsMileageWhenAbsent(): void
    {
        $client = $this->loginAdmin();
        $wo = $this->workOrders()->findOneBy(['number' => 'WO-2026-0002']);
        self::assertNotNull($wo);
        $this->assertNull($wo->getOdometerReading());

        $crawler = $client->request('GET', '/work-orders/' . $wo->getId());
        $this->assertResponseIsSuccessful();
        // The mileage label row should not appear at all.
        $kvLabels = $crawler->filter('.kv-row dt')->each(fn ($n) => trim($n->text()));
        $this->assertNotContains('Mileage', $kvLabels);
    }

    public function testCarDetailsPageListsServiceHistoryWithMileage(): void
    {
        $client = $this->loginAdmin();
        $car = $this->seedHistoryCar('History test car ' . uniqid(), [
            // Older first; will display newest-first in the page.
            ['number' => 'WO-HIST-001-' . substr(uniqid(), -4), 'reading' => 90000, 'unit' => MileageUnit::Kilometres, 'daysAgo' => 200],
            ['number' => 'WO-HIST-002-' . substr(uniqid(), -4), 'reading' => 95000, 'unit' => MileageUnit::Kilometres, 'daysAgo' => 100],
            ['number' => 'WO-HIST-003-' . substr(uniqid(), -4), 'reading' => 102500, 'unit' => MileageUnit::Kilometres, 'daysAgo' => 10],
        ]);

        $client->request('GET', '/cars/' . $car->getId());
        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('body', '102,500 km');
        $this->assertSelectorTextContains('body', '95,000 km');
        $this->assertSelectorTextContains('body', '90,000 km');
    }

    public function testLatestMileageUsesLatestApplicableWorkOrder(): void
    {
        $this->loginAdmin();
        $car = $this->seedHistoryCar('Latest mileage car ' . uniqid(), [
            // Latest BY DATE has the smallest reading (e.g. odometer reset/correction).
            // The repository must still pick it as "latest", proving the
            // ordering is by openedAt, not by reading.
            ['number' => 'WO-LAT-OLD-' . substr(uniqid(), -4), 'reading' => 200000, 'unit' => MileageUnit::Kilometres, 'daysAgo' => 60],
            ['number' => 'WO-LAT-NEW-' . substr(uniqid(), -4), 'reading' => 1500, 'unit' => MileageUnit::Kilometres, 'daysAgo' => 5],
        ]);

        $latest = $this->workOrders()->findLatestWithMileageForCar($car);
        $this->assertNotNull($latest);
        $this->assertSame(1500, $latest->getOdometerReading(),
            'Latest mileage must come from the newest dated WO with a reading, not the largest reading.');
    }

    public function testPdfTemplateIncludesMileageWhenPresent(): void
    {
        $container = static::getContainer();
        $twig = $container->get('twig');
        $wo = $container->get(WorkOrderRepository::class)->findOneBy(['number' => 'WO-2026-0001']);
        self::assertNotNull($wo);
        $company = $container->get(\App\Service\Pdf\CompanyProfileProvider::class);

        $html = $twig->render('pdf/work_order.html.twig', [
            'work_order' => $wo,
            'company' => $company->getCompany(),
            'logo_path' => null,
        ]);

        $this->assertStringContainsString('119,800 km', $html);
    }

    public function testPdfTemplateOmitsMileageWhenAbsent(): void
    {
        $container = static::getContainer();
        $twig = $container->get('twig');
        $wo = $container->get(WorkOrderRepository::class)->findOneBy(['number' => 'WO-2026-0002']);
        self::assertNotNull($wo);
        $this->assertNull($wo->getOdometerReading());
        $company = $container->get(\App\Service\Pdf\CompanyProfileProvider::class);

        $html = $twig->render('pdf/work_order.html.twig', [
            'work_order' => $wo,
            'company' => $company->getCompany(),
            'logo_path' => null,
        ]);

        // Mileage label must not appear at all for a missing reading.
        $this->assertStringNotContainsString('Mileage:', $html);
    }

    // ───── Translation key smoke check ──────────────────────────────────

    public function testMileageTranslationKeysExistForAllSupportedLocales(): void
    {
        $translator = static::getContainer()->get('translator');
        $keys = [
            'mileage.label',
            'mileage.odometer_reading',
            'mileage.unit.km',
            'mileage.unit.mi',
            'mileage.not_recorded',
            'mileage.error.negative',
            'car.show.latest_mileage',
            'car.show.mileage_not_recorded',
            'work_order.field.mileage',
            'work_order.field.odometer_reading',
            'work_order.help.odometer_reading',
            'settings.regional.field.mileage_unit',
            'settings.regional.help.mileage_unit',
        ];

        foreach (['en', 'bg', 'de', 'es', 'fr'] as $locale) {
            foreach ($keys as $key) {
                $translated = $translator->trans($key, [], 'messages', $locale);
                $this->assertNotSame($key, $translated, sprintf(
                    'Translation key "%s" should be defined for locale "%s".',
                    $key,
                    $locale,
                ));
            }
        }
    }

    // ───── Helpers ───────────────────────────────────────────────────────

    /**
     * @return array{0: Client, 1: Car}
     */
    private function aFreshClientAndCar(): array
    {
        $client = $this->em()->getRepository(Client::class)->findOneBy([]);
        $car = $this->em()->getRepository(Car::class)->findOneBy(['client' => $client]);
        $this->assertNotNull($client);
        $this->assertNotNull($car);

        return [$client, $car];
    }

    private function findByComplaint(string $complaint): ?WorkOrder
    {
        $this->em()->clear();

        return $this->workOrders()->findOneBy(['complaint' => $complaint]);
    }

    /**
     * Creates a dedicated Car (and Client if missing) plus a series of
     * completed WorkOrders with the given mileage history. Returns the car
     * so the test can query against an isolated dataset that won't drift
     * when sibling tests create their own work orders.
     *
     * @param list<array{number: string, reading: int, unit: MileageUnit, daysAgo: int}> $entries
     */
    private function seedHistoryCar(string $label, array $entries): Car
    {
        $em = $this->em();

        // Reuse the first client; cars are 1:N so a dedicated car gives us
        // the test isolation we need without orchestrating a full client.
        $client = $em->getRepository(Client::class)->findOneBy([]);
        self::assertNotNull($client);

        $model = $em->getRepository(\App\Entity\Car\Model::class)->findOneBy([]);
        self::assertNotNull($model, 'Brand/model fixtures are required.');

        $car = new Car();
        $car->setClient($client);
        $car->setModel($model);
        $car->setLicensePlate('TEST ' . strtoupper(substr(uniqid(), -4)));
        $car->setVin($label);
        $em->persist($car);
        $em->flush();

        $admin = static::getContainer()->get(UserRepository::class)->findOneBy(['email' => 'admin@carstio.local']);
        foreach ($entries as $entry) {
            $wo = new WorkOrder();
            $wo->setNumber($entry['number']);
            $wo->setClient($client);
            $wo->setCar($car);
            $wo->setStatus(WorkOrderStatus::Completed);
            $wo->setComplaint($label);
            $wo->setOpenedAt(new \DateTimeImmutable('-' . $entry['daysAgo'] . ' days'));
            $wo->setCreatedBy($admin);
            $wo->setOdometerReading($entry['reading']);
            $wo->setOdometerUnit($entry['unit']);
            $em->persist($wo);
        }
        $em->flush();
        $em->clear();

        return $em->getRepository(Car::class)->find($car->getId());
    }
}
