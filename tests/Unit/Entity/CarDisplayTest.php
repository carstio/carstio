<?php

namespace App\Tests\Unit\Entity;

use App\Entity\Car\Brand;
use App\Entity\Car\Car;
use App\Entity\Car\Model;
use PHPUnit\Framework\TestCase;

class CarDisplayTest extends TestCase
{
    public function testModelDisplayNameCombinesBrandAndModel(): void
    {
        $brand = (new Brand())->setName('Ford');
        $model = (new Model())->setName('Focus')->setBrand($brand);

        $this->assertSame('Ford Focus', $model->getDisplayName());
    }

    public function testModelDisplayNameFallsBackToModelNameWhenBrandMissing(): void
    {
        $model = (new Model())->setName('Focus');

        $this->assertSame('Focus', $model->getDisplayName());
    }

    public function testCarDisplayNameDelegatesToModel(): void
    {
        $brand = (new Brand())->setName('BMW');
        $model = (new Model())->setName('320d')->setBrand($brand);
        $car = (new Car())->setModel($model);

        $this->assertSame('BMW 320d', $car->getDisplayName());
    }

    public function testCarDisplayNameIsEmptyWhenNoModel(): void
    {
        $car = new Car();

        $this->assertSame('', $car->getDisplayName());
    }

    public function testBrandAddModelAssignsInverseSide(): void
    {
        $brand = (new Brand())->setName('Volkswagen');
        $model = (new Model())->setName('Golf');

        $brand->addModel($model);

        $this->assertSame($brand, $model->getBrand());
        $this->assertTrue($brand->getModels()->contains($model));
    }
}
