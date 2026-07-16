<?php

declare(strict_types=1);

namespace App\Tests\Functional\Controller;

use App\Repository\UserRepository;
use App\Repository\Workshop\WorkOrderRepository;
use App\Service\Settings\SettingsManager;
use App\Settings\SettingsRegistry;
use App\Tests\Support\Email\RecordingTransport;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Mime\Email;

class WorkOrderEmailTest extends WebTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        RecordingTransport::reset();
    }

    public function testAnonymousCannotSendEmail(): void
    {
        $client = static::createClient();
        $client->request('POST', '/work-orders/1/email');

        $this->assertResponseRedirects();
        $this->assertSame([], RecordingTransport::sent());
    }

    public function testGetMethodIsNotAllowed(): void
    {
        $client = $this->loginAdmin();
        $workOrder = $this->workOrderForAlice($client);
        $client->request('GET', '/work-orders/' . $workOrder->getId() . '/email');

        $this->assertSame(405, $client->getResponse()->getStatusCode());
    }

    public function testInvalidCsrfDoesNotSend(): void
    {
        $client = $this->loginAdmin();
        $this->configureSmtp();
        $workOrder = $this->workOrderForAlice($client);

        $client->request('POST', '/work-orders/' . $workOrder->getId() . '/email', [
            '_token' => 'wrong',
        ]);

        $this->assertResponseRedirects('/work-orders/' . $workOrder->getId());
        $this->assertSame([], RecordingTransport::sent());
    }

    public function testIncompleteSmtpSettingsShowsError(): void
    {
        $client = $this->loginAdmin();
        $this->clearSmtp();
        $workOrder = $this->workOrderForAlice($client);

        $this->submitSendEmail($client, $workOrder->getId());
        $this->assertResponseRedirects('/work-orders/' . $workOrder->getId());
        $client->followRedirect();
        $this->assertSelectorTextContains('.alert', 'incomplete');
        $this->assertSame([], RecordingTransport::sent());
    }

    public function testSuccessfulSendComposesEmailAndAttachesPdf(): void
    {
        $client = $this->loginAdmin();
        $this->configureSmtp();
        $workOrder = $this->workOrderForAlice($client);

        $this->submitSendEmail($client, $workOrder->getId());

        $this->assertResponseRedirects('/work-orders/' . $workOrder->getId());
        $client->followRedirect();
        $this->assertSelectorTextContains('.alert-success', 'Work order sent by email.');

        $sent = RecordingTransport::sent();
        $this->assertCount(1, $sent);
        $this->assertInstanceOf(Email::class, $sent[0]);

        $email = $sent[0];
        $this->assertSame('alice.smith@example.com', $email->getTo()[0]->getAddress());
        $this->assertSame('test-from@example.com', $email->getFrom()[0]->getAddress());
        $this->assertStringContainsString((string) $workOrder->getNumber(), (string) $email->getSubject());

        $attachments = $email->getAttachments();
        $this->assertCount(1, $attachments);
        $this->assertSame('application/pdf', $attachments[0]->getMediaType() . '/' . $attachments[0]->getMediaSubtype());
        $name = $attachments[0]->getPreparedHeaders()->get('content-disposition')?->getBodyAsString() ?? '';
        $this->assertStringContainsString('work-order-' . $workOrder->getNumber() . '.pdf', $name);
    }

    private function loginAdmin(): KernelBrowser
    {
        $client = static::createClient();
        $user = static::getContainer()->get(UserRepository::class)->findOneBy(['email' => 'admin@carstio.local']);
        $client->loginUser($user);

        return $client;
    }

    /**
     * Pick the work order for Alice - her client record has an email in fixtures.
     */
    private function workOrderForAlice(KernelBrowser $client): \App\Entity\Workshop\WorkOrder
    {
        $repo = static::getContainer()->get(WorkOrderRepository::class);
        foreach ($repo->findAll() as $wo) {
            if ($wo->getClient()?->getEmail() === 'alice.smith@example.com') {
                return $wo;
            }
        }

        self::fail('No work order for Alice found in fixtures.');
    }

    private function submitSendEmail(KernelBrowser $client, int $workOrderId): void
    {
        $crawler = $client->request('GET', '/work-orders/' . $workOrderId);
        $token = $crawler
            ->filter(sprintf('form[action="/work-orders/%d/email"] input[name="_token"]', $workOrderId))
            ->attr('value');
        $client->request('POST', '/work-orders/' . $workOrderId . '/email', ['_token' => $token]);
    }

    private function configureSmtp(): void
    {
        $manager = static::getContainer()->get(SettingsManager::class);
        $manager->setMany(SettingsRegistry::SECTION_EMAIL, [
            SettingsRegistry::KEY_SMTP_HOST => 'smtp.test.local',
            SettingsRegistry::KEY_SMTP_PORT => 587,
            SettingsRegistry::KEY_SMTP_ENCRYPTION => 'tls',
            SettingsRegistry::KEY_SMTP_USERNAME => 'tester',
            SettingsRegistry::KEY_SMTP_FROM_EMAIL => 'test-from@example.com',
            SettingsRegistry::KEY_SMTP_FROM_NAME => 'Carstio Test',
        ]);
    }

    private function clearSmtp(): void
    {
        $manager = static::getContainer()->get(SettingsManager::class);
        $manager->setMany(SettingsRegistry::SECTION_EMAIL, [
            SettingsRegistry::KEY_SMTP_HOST => '',
            SettingsRegistry::KEY_SMTP_PORT => '',
            SettingsRegistry::KEY_SMTP_FROM_EMAIL => '',
            SettingsRegistry::KEY_SMTP_FROM_NAME => '',
        ]);
    }
}
