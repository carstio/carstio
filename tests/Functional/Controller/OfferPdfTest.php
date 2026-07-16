<?php

namespace App\Tests\Functional\Controller;

use App\Repository\Sales\OfferRepository;
use App\Repository\UserRepository;
use App\Service\Settings\SettingsManager;
use App\Settings\SettingsRegistry;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class OfferPdfTest extends WebTestCase
{
    private function loginClient(string $email = 'admin@carstio.local'): KernelBrowser
    {
        $client = static::createClient();
        $user = static::getContainer()->get(UserRepository::class)->findOneBy(['email' => $email]);
        $client->loginUser($user);

        return $client;
    }

    private function firstOfferId(): int
    {
        $offer = static::getContainer()->get(OfferRepository::class)->findOneBy(['number' => 'OF-2026-0001']);
        self::assertNotNull($offer, 'Expected fixture offer OF-2026-0001 to exist.');

        return $offer->getId();
    }

    public function testAnonymousCannotDownloadOfferPdf(): void
    {
        $client = static::createClient();
        $offerId = $this->firstOfferId();
        $client->request('GET', '/offers/' . $offerId . '/pdf');

        $this->assertResponseRedirects('/login');
    }

    public function testAuthenticatedUserDownloadsOfferPdf(): void
    {
        $client = $this->loginClient();
        $client->request('GET', '/offers/' . $this->firstOfferId() . '/pdf');

        $this->assertResponseIsSuccessful();
        $this->assertSame('application/pdf', $client->getResponse()->headers->get('Content-Type'));

        $disposition = (string) $client->getResponse()->headers->get('Content-Disposition');
        $this->assertStringContainsString('attachment', $disposition);
        $this->assertStringContainsString('offer-OF-2026-0001.pdf', $disposition);

        $content = (string) $client->getResponse()->getContent();
        $this->assertStringStartsWith('%PDF-', $content);
    }

    public function testOfferPdfGeneratesWithoutLogo(): void
    {
        $client = $this->loginClient();

        static::getContainer()->get(SettingsManager::class)
            ->setFilePath(SettingsRegistry::SECTION_COMPANY, SettingsRegistry::KEY_LOGO_PATH, null);

        $client->request('GET', '/offers/' . $this->firstOfferId() . '/pdf');

        $this->assertResponseIsSuccessful();
        $this->assertSame('application/pdf', $client->getResponse()->headers->get('Content-Type'));
        $this->assertStringStartsWith('%PDF-', (string) $client->getResponse()->getContent());
    }
}
