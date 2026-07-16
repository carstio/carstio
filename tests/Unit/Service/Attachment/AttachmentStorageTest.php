<?php

namespace App\Tests\Unit\Service\Attachment;

use App\Entity\Attachment\Attachment;
use App\Enum\AttachmentOwnerType;
use App\Service\Attachment\AttachmentStorage;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class AttachmentStorageTest extends TestCase
{
    private string $storageRoot;
    private string $sourceDir;
    private Filesystem $filesystem;

    protected function setUp(): void
    {
        $this->filesystem = new Filesystem();
        $this->storageRoot = sys_get_temp_dir() . '/carstio_attachments_' . bin2hex(random_bytes(4));
        $this->sourceDir = sys_get_temp_dir() . '/carstio_attachments_src_' . bin2hex(random_bytes(4));
        $this->filesystem->mkdir($this->storageRoot);
        $this->filesystem->mkdir($this->sourceDir);
    }

    protected function tearDown(): void
    {
        if ($this->filesystem->exists($this->storageRoot)) {
            $this->filesystem->remove($this->storageRoot);
        }
        if ($this->filesystem->exists($this->sourceDir)) {
            $this->filesystem->remove($this->sourceDir);
        }
    }

    public function testStoreCreatesFileAndReturnsMetadata(): void
    {
        $file = $this->makeUploadedFile('hello.pdf', '%PDF-1.4 hello world');
        $storage = new AttachmentStorage($this->storageRoot);

        $result = $storage->store($file, AttachmentOwnerType::WorkOrder);

        self::assertSame('hello.pdf', $result['originalFilename']);
        self::assertNotSame('hello.pdf', $result['storedFilename'], 'Stored filename must not equal original.');
        self::assertStringStartsWith('work_order/', $result['storagePath']);
        self::assertSame('pdf', $result['extension']);
        self::assertGreaterThan(0, $result['size']);
        self::assertFileExists($this->storageRoot . '/' . $result['storagePath']);
    }

    public function testStoredPathFollowsOwnerYearMonthHashLayout(): void
    {
        $file = $this->makeUploadedFile('photo.jpg', "\xff\xd8\xff\xe0fakejpg");
        $storage = new AttachmentStorage($this->storageRoot);

        $result = $storage->store($file, AttachmentOwnerType::WorkOrder);

        $now = new \DateTimeImmutable();
        $segments = explode('/', $result['storagePath']);
        self::assertCount(5, $segments, 'Path should have ownerType/Y/m/hashPrefix/storedFilename.');
        self::assertSame('work_order', $segments[0]);
        self::assertSame($now->format('Y'), $segments[1]);
        self::assertSame($now->format('m'), $segments[2]);
        self::assertSame(2, strlen($segments[3]));
        self::assertSame($result['storedFilename'], $segments[4]);
    }

    public function testDeleteRemovesPhysicalFile(): void
    {
        $file = $this->makeUploadedFile('doc.pdf', '%PDF-1.4 stuff');
        $storage = new AttachmentStorage($this->storageRoot);
        $result = $storage->store($file, AttachmentOwnerType::WorkOrder);

        $attachment = (new Attachment())
            ->setOwnerType(AttachmentOwnerType::WorkOrder)
            ->setOwnerId(1)
            ->setOriginalFilename($result['originalFilename'])
            ->setStoredFilename($result['storedFilename'])
            ->setStoragePath($result['storagePath'])
            ->setMimeType($result['mimeType'])
            ->setSize($result['size']);

        self::assertTrue($storage->exists($attachment));

        $storage->delete($attachment);

        self::assertFalse($storage->exists($attachment));
    }

    public function testDeleteToleratesMissingFile(): void
    {
        $storage = new AttachmentStorage($this->storageRoot);
        $attachment = (new Attachment())
            ->setOwnerType(AttachmentOwnerType::WorkOrder)
            ->setOwnerId(1)
            ->setOriginalFilename('gone.pdf')
            ->setStoredFilename('gone.pdf')
            ->setStoragePath('work_order/2026/04/00/gone.pdf')
            ->setMimeType('application/pdf')
            ->setSize(0);

        $storage->delete($attachment);
        self::assertFalse($storage->exists($attachment));
    }

    private function makeUploadedFile(string $originalName, string $contents): UploadedFile
    {
        $tmp = $this->sourceDir . '/' . bin2hex(random_bytes(4)) . '_' . $originalName;
        file_put_contents($tmp, $contents);

        // test=true means UploadedFile won't try to verify it came from an
        // actual HTTP upload; the file is moved with rename(), which keeps
        // tests fully local.
        return new UploadedFile($tmp, $originalName, null, null, true);
    }
}
