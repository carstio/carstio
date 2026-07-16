<?php

namespace App\Tests\Unit\Service;

use App\Entity\Car\Brand;
use App\Entity\Car\Car;
use App\Entity\Car\Model;
use App\Service\Formatting\CarLabelFormatter;
use PHPUnit\Framework\TestCase;

class CarLabelFormatterTest extends TestCase
{
    private CarLabelFormatter $formatter;

    protected function setUp(): void
    {
        $this->formatter = new CarLabelFormatter();
    }

    public function testReturnsEmptyForNullCar(): void
    {
        $this->assertSame('', $this->formatter->label(null));
    }

    public function testReturnsEmptyForCarWithNoModelAndNoPlate(): void
    {
        $this->assertSame('', $this->formatter->label(new Car()));
    }

    public function testReturnsBrandModelWhenPlateMissing(): void
    {
        $car = $this->car('BMW', '320d');

        $this->assertSame('BMW 320d', $this->formatter->label($car));
    }

    public function testParenthesizesPlateAlongsideBrandModel(): void
    {
        $car = $this->car('Ford', 'Focus')->setLicensePlate('CA 1234 AB');

        $this->assertSame('Ford Focus (CA 1234 AB)', $this->formatter->label($car));
    }

    public function testReturnsPlateOnlyWhenModelMissing(): void
    {
        $car = (new Car())->setLicensePlate('CB 5678 CD');

        $this->assertSame('CB 5678 CD', $this->formatter->label($car));
    }

    public function testIgnoresEmptyPlate(): void
    {
        $car = $this->car('Toyota', 'Corolla')->setLicensePlate('');

        $this->assertSame('Toyota Corolla', $this->formatter->label($car));
    }

    private function car(string $brandName, string $modelName): Car
    {
        $brand = (new Brand())->setName($brandName);
        $model = (new Model())->setName($modelName)->setBrand($brand);

        return (new Car())->setModel($model);
    }
}
