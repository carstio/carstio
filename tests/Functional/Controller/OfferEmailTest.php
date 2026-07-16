<?php

declare(strict_types=1);

namespace App\Tests\Functional\Controller;

use App\Repository\Sales\OfferRepository;
use App\Repository\UserRepository;
use App\Service\Settings\SettingsManager;
use App\Settings\SettingsRegistry;
use App\Tests\Support\Email\RecordingTransport;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Mime\Email;

class OfferEmailTest extends WebTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        RecordingTransport::reset();
    }

    public function testAnonymousCannotSendEmail(): void
    {
        $client = static::createClient();
        $client->request('POST', '/offers/1/email');

        $this->assertResponseRedirects();
        $this->assertSame([], RecordingTransport::sent());
    }

    public function testGetMethodIsNotAllowed(): void
    {
        $client = $this->loginAdmin();
        $offer = $this->offerByNumber($client, 'OF-2026-0002');
        $client->request('GET', '/offers/' . $offer->getId() . '/email');

        $this->assertSame(405, $client->getResponse()->getStatusCode());
    }

    public function testInvalidCsrfDoesNotSend(): void
    {
        $client = $this->loginAdmin();
        $this->configureSmtp();
        $offer = $this->offerByNumber($client, 'OF-2026-0002');

        $client->request('POST', '/offers/' . $offer->getId() . '/email', [
            '_token' => 'wrong',
        ]);

        $this->assertResponseRedirects('/offers/' . $offer->getId());
        $this->assertSame([], RecordingTransport::sent());
    }

    public function testMissingClientEmailShowsError(): void
    {
        $client = $this->loginAdmin();
        $this->configureSmtp();
        // Bob has no email in fixtures (ClientFixtures).
        $offer = $this->offerByNumber($client, 'OF-2026-0001');
        self::assertNull($offer->getClient()?->getEmail(), 'Fixture must have Bob without email.');

        $this->submitSendEmail($client, $offer->getId());

        $this->assertResponseRedirects('/offers/' . $offer->getId());
        $client->followRedirect();
        $this->assertSelectorTextContains('.alert', 'does not have an email address');
        $this->assertSame([], RecordingTransport::sent());
    }

    public function testIncompleteSmtpSettingsShowsError(): void
    {
        $client = $this->loginAdmin();
        $this->clearSmtp();
        $offer = $this->offerByNumber($client, 'OF-2026-0002');

        $this->submitSendEmail($client, $offer->getId());

        $this->assertResponseRedirects('/offers/' . $offer->getId());
        $client->followRedirect();
        $this->assertSelectorTextContains('.alert', 'incomplete');
        $this->assertSame([], RecordingTransport::sent());
    }

    public function testSuccessfulSendComposesEmailAndAttachesPdf(): void
    {
        $client = $this->loginAdmin();
        $this->configureSmtp();
        // Pin the company name so subject assertion isn't fragile to other tests.
        static::getContainer()->get(SettingsManager::class)->set(
            SettingsRegistry::SECTION_COMPANY,
            SettingsRegistry::KEY_COMPANY_NAME,
            'Carstio Workshop Demo',
        );
        $offer = $this->offerByNumber($client, 'OF-2026-0002');

        $this->submitSendEmail($client, $offer->getId());

        $this->assertResponseRedirects('/offers/' . $offer->getId());
        $client->followRedirect();
        $this->assertSelectorTextContains('.alert-success', 'Offer sent by email.');

        $sent = RecordingTransport::sent();
        $this->assertCount(1, $sent);
        $this->assertInstanceOf(Email::class, $sent[0]);

        $email = $sent[0];
        $this->assertSame('alice.smith@example.com', $email->getTo()[0]->getAddress());
        $this->assertSame('test-from@example.com', $email->getFrom()[0]->getAddress());
        $this->assertStringContainsString('OF-2026-0002', (string) $email->getSubject());
        $this->assertStringContainsString('Carstio Workshop Demo', (string) $email->getSubject());
        $this->assertNotEmpty($email->getHtmlBody());
        $this->assertNotEmpty($email->getTextBody());

        $attachments = $email->getAttachments();
        $this->assertCount(1, $attachments);
        $this->assertSame('application/pdf', $attachments[0]->getMediaType() . '/' . $attachments[0]->getMediaSubtype());
        $name = $attachments[0]->getPreparedHeaders()->get('content-disposition')?->getBodyAsString() ?? '';
        $this->assertStringContainsString('offer-OF-2026-0002.pdf', $name);
    }

    private function submitSendEmail(KernelBrowser $client, int $offerId): void
    {
        // Render the show page first so a session exists and the stateful
        // CSRF token gets minted, then post directly with the token. This
        // also works when the button is disabled (no client email case).
        $crawler = $client->request('GET', '/offers/' . $offerId);
        $token = $crawler
            ->filter(sprintf('form[action="/offers/%d/email"] input[name="_token"]', $offerId))
            ->attr('value');
        $client->request('POST', '/offers/' . $offerId . '/email', ['_token' => $token]);
    }

    private function loginAdmin(): KernelBrowser
    {
        $client = static::createClient();
        $user = static::getContainer()->get(UserRepository::class)->findOneBy(['email' => 'admin@carstio.local']);
        $client->loginUser($user);

        return $client;
    }

    private function offerByNumber(KernelBrowser $client, string $number): \App\Entity\Sales\Offer
    {
        $offer = static::getContainer()->get(OfferRepository::class)->findOneBy(['number' => $number]);
        self::assertNotNull($offer);

        return $offer;
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
