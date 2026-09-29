<?php

namespace App\Tests\Unit\Service;

use App\Service\EntityDeleter;
use Doctrine\DBAL\Driver\Exception as DriverExceptionInterface;
use Doctrine\DBAL\Exception\ForeignKeyConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class EntityDeleterTest extends TestCase
{
    public function testReturnsTrueWhenRemoveSucceeds(): void
    {
        $entity = new \stdClass();

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(self::once())->method('remove')->with($entity);
        $em->expects(self::once())->method('flush');

        $deleter = new EntityDeleter($em, $this->createStub(LoggerInterface::class));

        self::assertTrue($deleter->delete($entity));
    }

    public function testReturnsFalseAndLogsWhenForeignKeyIsViolated(): void
    {
        $entity = new \stdClass();
        $fkException = new ForeignKeyConstraintViolationException(
            $this->createStub(DriverExceptionInterface::class),
            null,
        );

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(self::once())->method('remove')->with($entity);
        $em->method('flush')->willThrowException($fkException);

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('warning');

        $deleter = new EntityDeleter($em, $logger);

        self::assertFalse($deleter->delete($entity));
    }
}
