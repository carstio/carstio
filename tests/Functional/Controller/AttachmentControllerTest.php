<?php

namespace App\Tests\Functional\Controller;

use App\Entity\Workshop\WorkOrder;
use App\Repository\Attachment\AttachmentRepository;
use App\Repository\UserRepository;
use App\Repository\Workshop\WorkOrderRepository;
use App\Service\Attachment\AttachmentStorage;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class AttachmentControllerTest extends WebTestCase
{
    private string $tmpDir;
    private Filesystem $filesystem;

    protected function setUp(): void
    {
        parent::setUp();
        $this->filesystem = new Filesystem();
        $this->tmpDir = sys_get_temp_dir() . '/carstio_attachment_uploads_' . bin2hex(random_bytes(4));
        $this->filesystem->mkdir($this->tmpDir);
    }

    protected function tearDown(): void
    {
        if (isset($this->filesystem) && $this->filesystem->exists($this->tmpDir)) {
            $this->filesystem->remove($this->tmpDir);
        }
        parent::tearDown();
    }

    public function testAnonymousCannotUploadToWorkOrder(): void
    {
        $client = static::createClient();
        $workOrder = $this->getWorkOrder($client);

        $client->request('POST', '/work-orders/' . $workOrder->getId() . '/attachments');
        $this->assertResponseRedirects('/login');
    }

    public function testAnonymousCannotDownloadAttachment(): void
    {
        $client = static::createClient();
        $client->request('GET', '/attachments/1/download');
        $this->assertResponseRedirects('/login');
    }

    public function testAnonymousCannotDeleteAttachment(): void
    {
        $client = static::createClient();
        $client->request('POST', '/attachments/1/delete');
        $this->assertResponseRedirects('/login');
    }

    public function testWorkOrderShowRendersAttachmentsSection(): void
    {
        $client = $this->loginAdmin();
        $workOrder = $this->getWorkOrder($client);

        $crawler = $client->request('GET', '/work-orders/' . $workOrder->getId());

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('body', 'Attachments');
        $this->assertSelectorExists('input[name="attachment_upload[file]"]');
        $this->assertSelectorExists('select[name="attachment_upload[category]"]');
    }

    public function testUploadRejectsDisallowedMimeType(): void
    {
        $client = $this->loginAdmin();
        $workOrder = $this->getWorkOrder($client);
        $repo = static::getContainer()->get(AttachmentRepository::class);
        $before = count($repo->findAll());

        $crawler = $client->request('GET', '/work-orders/' . $workOrder->getId());
        $form = $crawler->filter('form[name="attachment_upload"]')->form();

        $form['attachment_upload[file]']->upload($this->makeTempFile('payload.html', '<html>nope</html>'));
        $form['attachment_upload[category]'] = 'document';
        $client->submit($form);

        // Form errors -> redirect with error flash, no DB row.
        $this->assertResponseRedirects('/work-orders/' . $workOrder->getId());
        $client->followRedirect();

        self::assertSame($before, count($repo->findAll()), 'No attachment should be persisted on rejected upload.');
    }

    public function testUploadAcceptsValidPdf(): void
    {
        $client = $this->loginAdmin();
        $workOrder = $this->getWorkOrder($client);
        $repo = static::getContainer()->get(AttachmentRepository::class);
        $beforeIds = array_map(static fn ($a) => $a->getId(), $repo->findAll());

        $crawler = $client->request('GET', '/work-orders/' . $workOrder->getId());
        $form = $crawler->filter('form[name="attachment_upload"]')->form();
        $form['attachment_upload[file]']->upload($this->makeTempFile('invoice.pdf', $this->minimalPdf()));
        $form['attachment_upload[category]'] = 'document';
        $form['attachment_upload[description]'] = 'Customer invoice';
        $client->submit($form);

        $this->assertResponseRedirects('/work-orders/' . $workOrder->getId());

        $after = $repo->findAll();
        self::assertCount(count($beforeIds) + 1, $after);

        $created = null;
        foreach ($after as $attachment) {
            if (!in_array($attachment->getId(), $beforeIds, true)) {
                $created = $attachment;
                break;
            }
        }
        self::assertNotNull($created);
        self::assertSame('invoice.pdf', $created->getOriginalFilename());
        self::assertSame('document', $created->getCategory()->value);
        self::assertSame($workOrder->getId(), $created->getOwnerId());
        self::assertSame('work_order', $created->getOwnerType()->value);
        self::assertSame('Customer invoice', $created->getDescription());

        // File must live under var/storage (outside public/).
        $storage = static::getContainer()->get(AttachmentStorage::class);
        $absolute = $storage->absolutePath($created);
        self::assertFileExists($absolute);
        self::assertStringContainsString('/var/storage/', $absolute);
        self::assertStringNotContainsString('/public/', $absolute);
    }

    public function testDownloadReturnsOriginalFile(): void
    {
        $client = $this->loginAdmin();
        $created = $this->uploadFixturePdf($client);

        $client->request('GET', '/attachments/' . $created->getId() . '/download');

        $this->assertResponseIsSuccessful();
        $disposition = (string) $client->getResponse()->headers->get('Content-Disposition');
        self::assertStringContainsString('attachment', $disposition);
        self::assertStringContainsString($created->getOriginalFilename(), $disposition);
    }

    public function testDeleteRemovesRecordAndPhysicalFile(): void
    {
        $client = $this->loginAdmin();
        $workOrder = $this->getWorkOrder($client);
        $created = $this->uploadFixturePdf($client, $workOrder);
        $createdId = $created->getId();

        $storage = static::getContainer()->get(AttachmentStorage::class);
        $absolutePath = $storage->absolutePath($created);
        self::assertFileExists($absolutePath);

        $this->submitDeleteForm($client, $workOrder->getId(), $createdId);

        $this->assertResponseRedirects();

        static::getContainer()->get('doctrine')->getManager()->clear();
        $repo = static::getContainer()->get(AttachmentRepository::class);
        self::assertNull($repo->find($createdId), 'Attachment should be removed from the DB.');
        self::assertFileDoesNotExist($absolutePath, 'Physical file should be removed.');
    }

    public function testDeleteHandlesMissingPhysicalFile(): void
    {
        $client = $this->loginAdmin();
        $workOrder = $this->getWorkOrder($client);
        $created = $this->uploadFixturePdf($client, $workOrder);
        $createdId = $created->getId();

        $storage = static::getContainer()->get(AttachmentStorage::class);
        $absolutePath = $storage->absolutePath($created);
        // Simulate a missing physical file (someone cleaned up storage by hand).
        $this->filesystem->remove($absolutePath);
        self::assertFileDoesNotExist($absolutePath);

        $this->submitDeleteForm($client, $workOrder->getId(), $createdId);

        $this->assertResponseRedirects();
        static::getContainer()->get('doctrine')->getManager()->clear();
        $repo = static::getContainer()->get(AttachmentRepository::class);
        self::assertNull($repo->find($createdId), 'Attachment record should be removed even if physical file is missing.');
    }

    private function submitDeleteForm(KernelBrowser $client, int $workOrderId, int $attachmentId): void
    {
        $crawler = $client->request('GET', '/work-orders/' . $workOrderId);
        $form = $crawler->filter('form[action="/attachments/' . $attachmentId . '/delete"]')->form();
        $client->submit($form);
    }

    public function testUploadedAttachmentAppearsInWorkOrderShow(): void
    {
        $client = $this->loginAdmin();
        $workOrder = $this->getWorkOrder($client);
        $created = $this->uploadFixturePdf($client, $workOrder);

        $client->request('GET', '/work-orders/' . $workOrder->getId());

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('body', $created->getOriginalFilename());
    }

    private function loginAdmin(): KernelBrowser
    {
        $client = static::createClient();
        $user = static::getContainer()->get(UserRepository::class)->findOneBy(['email' => 'admin@carstio.local']);
        $client->loginUser($user);

        return $client;
    }

    private function getWorkOrder(KernelBrowser $client): WorkOrder
    {
        $repo = static::getContainer()->get(WorkOrderRepository::class);
        $workOrder = $repo->findOneBy(['number' => 'WO-2026-0001']);
        self::assertNotNull($workOrder, 'Fixture work order WO-2026-0001 must exist.');

        return $workOrder;
    }

    private function uploadFixturePdf(KernelBrowser $client, ?WorkOrder $workOrder = null): \App\Entity\Attachment\Attachment
    {
        $workOrder ??= $this->getWorkOrder($client);
        $repo = static::getContainer()->get(AttachmentRepository::class);
        $beforeIds = array_map(static fn ($a) => $a->getId(), $repo->findAll());

        $crawler = $client->request('GET', '/work-orders/' . $workOrder->getId());
        $form = $crawler->filter('form[name="attachment_upload"]')->form();
        $form['attachment_upload[file]']->upload($this->makeTempFile('report.pdf', $this->minimalPdf()));
        $form['attachment_upload[category]'] = 'diagnostic';
        $client->submit($form);
        $this->assertResponseRedirects('/work-orders/' . $workOrder->getId());

        foreach ($repo->findAll() as $attachment) {
            if (!in_array($attachment->getId(), $beforeIds, true)) {
                return $attachment;
            }
        }
        self::fail('Failed to upload fixture attachment.');
    }

    private function makeTempFile(string $name, string $contents): string
    {
        // Keep the basename intact so UploadedFile preserves the
        // original filename - DomCrawler reads it from basename(path).
        $dir = $this->tmpDir . '/' . bin2hex(random_bytes(4));
        $this->filesystem->mkdir($dir);
        $path = $dir . '/' . $name;
        file_put_contents($path, $contents);

        return $path;
    }

    private function minimalPdf(): string
    {
        // Smallest realistic PDF stub. Mime detection in Symfony only
        // needs the leading "%PDF-" signature.
        return "%PDF-1.4\n%\xe2\xe3\xcf\xd3\n1 0 obj<<>>endobj\ntrailer<<>>\n%%EOF";
    }
}
