<?php

declare(strict_types=1);

namespace App\Tests\Unit\Entity;

use App\Entity\Car\Brand;
use App\Entity\Car\Model;
use PHPUnit\Framework\TestCase;

class BrandTest extends TestCase
{
    public function testAddModelSetsInverseSide(): void
    {
        $brand = new Brand();
        $brand->setName('Toyota');
        $model = new Model();
        $model->setName('Corolla');

        $brand->addModel($model);

        $this->assertTrue($brand->getModels()->contains($model));
        $this->assertSame($brand, $model->getBrand());
    }

    public function testRemoveModelNullsInverseSide(): void
    {
        $brand = new Brand();
        $brand->setName('Toyota');
        $model = new Model();
        $model->setName('Corolla');

        $brand->addModel($model);
        $brand->removeModel($model);

        $this->assertFalse($brand->getModels()->contains($model));
        $this->assertNull($model->getBrand(),
            'removeModel must null the inverse side to avoid leaving a dangling brand reference.');
    }

    public function testRemoveModelLeavesUnrelatedModelsAlone(): void
    {
        $brand = new Brand();
        $brand->setName('Toyota');
        $kept = new Model();
        $kept->setName('Yaris');
        $removed = new Model();
        $removed->setName('Avensis');

        $brand->addModel($kept);
        $brand->addModel($removed);
        $brand->removeModel($removed);

        $this->assertSame($brand, $kept->getBrand());
        $this->assertNull($removed->getBrand());
    }
}
