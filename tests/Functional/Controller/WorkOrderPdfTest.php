<?php

namespace App\Tests\Functional\Controller;

use App\Repository\UserRepository;
use App\Repository\Workshop\WorkOrderRepository;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class WorkOrderPdfTest extends WebTestCase
{
    private function loginClient(string $email = 'admin@carstio.local'): KernelBrowser
    {
        $client = static::createClient();
        $user = static::getContainer()->get(UserRepository::class)->findOneBy(['email' => $email]);
        $client->loginUser($user);

        return $client;
    }

    private function firstWorkOrderId(): int
    {
        $workOrder = static::getContainer()->get(WorkOrderRepository::class)->findOneBy(['number' => 'WO-2026-0001']);
        self::assertNotNull($workOrder, 'Expected fixture work order WO-2026-0001 to exist.');

        return $workOrder->getId();
    }

    public function testAnonymousCannotDownloadWorkOrderPdf(): void
    {
        $client = static::createClient();
        $id = $this->firstWorkOrderId();
        $client->request('GET', '/work-orders/' . $id . '/pdf');

        $this->assertResponseRedirects('/login');
    }

    public function testAuthenticatedUserDownloadsWorkOrderPdf(): void
    {
        $client = $this->loginClient();
        $client->request('GET', '/work-orders/' . $this->firstWorkOrderId() . '/pdf');

        $this->assertResponseIsSuccessful();
        $this->assertSame('application/pdf', $client->getResponse()->headers->get('Content-Type'));

        $disposition = (string) $client->getResponse()->headers->get('Content-Disposition');
        $this->assertStringContainsString('attachment', $disposition);
        $this->assertStringContainsString('work-order-WO-2026-0001.pdf', $disposition);

        $this->assertStringStartsWith('%PDF-', (string) $client->getResponse()->getContent());
    }
}
