<?php

namespace App\Tests\Functional\Controller;

use App\Repository\UserRepository;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class LocaleControllerTest extends WebTestCase
{
    /**
     * Extracts the locale-switch CSRF token from the rendered language
     * switcher on the given path, then POSTs to the locale endpoint.
     */
    private function switchLocale(KernelBrowser $client, string $locale, string $bootstrapPath = '/login', string $referer = '/login'): void
    {
        $crawler = $client->request('GET', $bootstrapPath, [], [], ['HTTP_REFERER' => $referer]);
        $token = $crawler->filter('#language-switcher-form input[name="_token"]')->first()->attr('value');

        $client->request('POST', '/locale/' . $locale, ['_token' => $token], [], ['HTTP_REFERER' => $referer]);
    }

    public function testGuestSwitchStoresLocaleInSession(): void
    {
        $client = static::createClient();
        $this->switchLocale($client, 'fr');

        $this->assertResponseRedirects('/login');

        $session = $client->getRequest()->getSession();
        $this->assertSame('fr', $session->get('_locale'));

        $crawler = $client->followRedirect();
        $this->assertResponseIsSuccessful();
        $this->assertStringContainsString('Se connecter', $crawler->filter('button.btn-primary')->text());
    }

    public function testUnsupportedLocaleIsIgnored(): void
    {
        $client = static::createClient();
        $this->switchLocale($client, 'zz');

        $this->assertResponseRedirects('/login');
        $session = $client->getRequest()->getSession();
        $this->assertNull($session->get('_locale'));
    }

    public function testRefererFromOtherHostIsIgnoredForRedirect(): void
    {
        $client = static::createClient();
        $this->switchLocale($client, 'de', referer: 'https://evil.example.com/somewhere');

        $this->assertResponseRedirects('/login');
    }

    public function testGetMethodIsRejected(): void
    {
        $client = static::createClient();
        $client->request('GET', '/locale/fr', [], [], ['HTTP_REFERER' => '/login']);

        $this->assertSame(405, $client->getResponse()->getStatusCode());
    }

    public function testInvalidCsrfTokenIsRejected(): void
    {
        $client = static::createClient();
        $client->request('GET', '/login');
        $client->request('POST', '/locale/fr', ['_token' => 'invalid'], [], ['HTTP_REFERER' => '/login']);

        $this->assertResponseRedirects('/login');
        $session = $client->getRequest()->getSession();
        $this->assertNull($session->get('_locale'),
            'A bogus CSRF token must not flip the locale.');
    }

    public function testAuthenticatedSwitchSavesPreferredLocaleOnUser(): void
    {
        $client = static::createClient();
        $container = static::getContainer();
        $em = $container->get(\Doctrine\ORM\EntityManagerInterface::class);
        $userRepository = $container->get(UserRepository::class);

        $admin = $userRepository->findOneBy(['email' => 'admin@carstio.local']);
        $this->assertNotNull($admin);
        $admin->setPreferredLocale(null);
        $em->flush();

        $client->loginUser($admin);
        $this->switchLocale($client, 'es', bootstrapPath: '/', referer: '/');
        $this->assertResponseRedirects('/');

        try {
            $em->clear();
            $reloaded = $userRepository->findOneBy(['email' => 'admin@carstio.local']);
            $this->assertSame('es', $reloaded->getPreferredLocale());
        } finally {
            // Reset via a DBAL UPDATE so the cleanup is independent of the
            // ORM identity map and survives test failure paths.
            $em->getConnection()->executeStatement(
                'UPDATE app_user SET preferred_locale = NULL WHERE email = :email',
                ['email' => 'admin@carstio.local'],
            );
        }
    }

    public function testLoginPageShowsLanguageSwitcher(): void
    {
        $client = static::createClient();
        $crawler = $client->request('GET', '/login');

        $this->assertResponseIsSuccessful();
        $this->assertGreaterThan(0, $crawler->filter('.language-switcher')->count());
        $this->assertGreaterThan(0, $crawler->filter('button[formaction="/locale/bg"]')->count());
        $this->assertGreaterThan(0, $crawler->filter('button[formaction="/locale/de"]')->count());
        $this->assertGreaterThan(0, $crawler->filter('button[formaction="/locale/es"]')->count());
        $this->assertGreaterThan(0, $crawler->filter('button[formaction="/locale/fr"]')->count());
    }
}
