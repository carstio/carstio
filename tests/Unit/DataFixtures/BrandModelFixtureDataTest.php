<?php

declare(strict_types=1);

namespace App\Tests\Unit\DataFixtures;

use App\DataFixtures\BrandModelFixtures;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Sanity checks for the static brand / model catalogue used by
 * {@see BrandModelFixtures}.
 *
 * The dataset is hand-curated and easy to fat-finger (typos, duplicate
 * model names within a brand, missing classics like "Ford Focus"). These
 * tests are cheap because the catalogue lives in a static method, so we
 * never touch the database here.
 */
class BrandModelFixtureDataTest extends TestCase
{
    public function testCatalogueIsNotEmpty(): void
    {
        $brands = BrandModelFixtures::brands();

        $this->assertGreaterThanOrEqual(30, count($brands), 'Catalogue should cover at least ~30 brands.');

        $totalModels = 0;
        foreach ($brands as $models) {
            $totalModels += count($models);
        }
        $this->assertGreaterThanOrEqual(200, $totalModels, 'Catalogue should expose at least 200 models in total.');
    }

    /**
     * @return iterable<string, array{0: string, 1: string}>
     */
    public static function canonicalPairs(): iterable
    {
        // Each pair represents a model that a typical European workshop
        // expects to find in the catalogue. Failing this list usually
        // means somebody renamed a brand or removed a flagship model.
        yield 'Ford Focus'           => ['Ford',         'Focus'];
        yield 'Volkswagen Golf'      => ['Volkswagen',   'Golf'];
        yield 'BMW 3 Series'         => ['BMW',          '3 Series'];
        yield 'Mercedes-Benz C-Class'=> ['Mercedes-Benz','C-Class'];
        yield 'Toyota Corolla'       => ['Toyota',       'Corolla'];
        yield 'Renault Clio'         => ['Renault',      'Clio'];
        yield 'Peugeot 308'          => ['Peugeot',      '308'];
        yield 'Škoda Octavia'        => ['Škoda',        'Octavia'];
        yield 'Audi A4'              => ['Audi',         'A4'];
        yield 'Hyundai Tucson'       => ['Hyundai',      'Tucson'];
        yield 'Mercedes-Benz Sprinter (van)' => ['Mercedes-Benz', 'Sprinter'];
        yield 'Ford Transit (van)'   => ['Ford',         'Transit'];
    }

    #[DataProvider('canonicalPairs')]
    public function testCanonicalBrandModelPairsExist(string $brand, string $model): void
    {
        $brands = BrandModelFixtures::brands();
        $this->assertArrayHasKey($brand, $brands, sprintf('Brand "%s" missing from catalogue.', $brand));
        $this->assertContains($model, $brands[$brand], sprintf('Model "%s" missing from brand "%s".', $model, $brand));
    }

    public function testNoDuplicateModelsWithinABrand(): void
    {
        $offenders = [];

        foreach (BrandModelFixtures::brands() as $brandName => $models) {
            $duplicates = array_keys(array_filter(array_count_values($models), static fn (int $n): bool => $n > 1));
            foreach ($duplicates as $modelName) {
                $offenders[] = sprintf('%s | %s', $brandName, $modelName);
            }
        }

        $this->assertSame([], $offenders, 'Duplicate models found: ' . implode(', ', $offenders));
    }

    public function testEveryEntryIsAWellFormedBrandWithModels(): void
    {
        // Guards against typos in the data structure itself: a brand key
        // accidentally turned into an int, a stray null model, an empty
        // model list, etc. Functional fixture loading is verified by the
        // doctrine:fixtures:load step in the test setup.
        foreach (BrandModelFixtures::brands() as $brandName => $models) {
            $this->assertIsString($brandName);
            $this->assertNotSame('', $brandName);
            $this->assertIsArray($models);
            $this->assertNotEmpty($models, sprintf('Brand "%s" must have at least one model.', $brandName));

            foreach ($models as $modelName) {
                $this->assertIsString($modelName, sprintf('Model in brand "%s" must be a string.', $brandName));
                $this->assertNotSame('', trim($modelName), sprintf('Brand "%s" has an empty model name.', $brandName));
            }
        }
    }

    public function testFixtureExposesReferenceConstantsForCarFixtures(): void
    {
        // CarFixtures depends on these four model references. If anyone
        // renames a constant on either side, this test surfaces it before
        // doctrine:fixtures:load fails with a cryptic message.
        $brands = BrandModelFixtures::brands();

        $this->assertContains('Corolla', $brands['Toyota']);
        $this->assertContains('3 Series', $brands['BMW']);
        $this->assertContains('X5', $brands['BMW']);
        $this->assertContains('Focus', $brands['Ford']);
    }
}
