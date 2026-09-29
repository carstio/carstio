<?php

namespace App\Tests\Functional\Controller;

use App\Repository\UserRepository;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class SecurityControllerTest extends WebTestCase
{
    public function testLoginPageLoads(): void
    {
        $client = static::createClient();
        $client->request('GET', '/login');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorExists('form');
        $this->assertSelectorExists('input[name="_username"]');
        $this->assertSelectorExists('input[name="_password"]');
    }

    public function testProtectedRoutesRedirectToLogin(): void
    {
        $client = static::createClient();

        $protectedRoutes = ['/', '/clients', '/cars', '/parts', '/work-orders', '/payments', '/settings', '/settings/company'];

        foreach ($protectedRoutes as $route) {
            $client->request('GET', $route);
            $this->assertResponseRedirects('/login', null, sprintf('Route %s should redirect to login', $route));
        }
    }

    public function testLoginWithValidCredentials(): void
    {
        $client = static::createClient();
        $client->request('GET', '/login');

        $client->submitForm('Log in', [
            '_username' => 'admin@carstio.local',
            '_password' => 'admin',
        ]);

        $this->assertResponseRedirects('/');
        $client->followRedirect();
        $this->assertResponseIsSuccessful();
    }

    public function testLoginWithInvalidCredentials(): void
    {
        $client = static::createClient();
        $client->request('GET', '/login');

        $client->submitForm('Log in', [
            '_username' => 'admin@carstio.local',
            '_password' => 'wrongpassword',
        ]);

        $this->assertResponseRedirects('/login');
        $client->followRedirect();
        $this->assertSelectorExists('.alert-danger');
    }

    public function testLoggedInUserRedirectedFromLogin(): void
    {
        $client = static::createClient();

        // Login first
        $client->request('GET', '/login');
        $client->submitForm('Log in', [
            '_username' => 'admin@carstio.local',
            '_password' => 'admin',
        ]);
        $client->followRedirect();

        // Visit login page again
        $client->request('GET', '/login');
        $this->assertResponseRedirects('/');
    }

    public function testLogoutViaHeaderFormEndsTheSession(): void
    {
        $client = static::createClient();
        $user = static::getContainer()->get(UserRepository::class)->findOneBy(['email' => 'admin@carstio.local']);
        $client->loginUser($user);

        $crawler = $client->request('GET', '/');
        $this->assertResponseIsSuccessful();

        $form = $crawler->filter('form[action="/logout"]')->form();
        $client->submit($form);
        $this->assertResponseRedirects('/login');

        $client->request('GET', '/');
        $this->assertResponseRedirects('/login', null, 'The session must be gone after logging out.');
    }

    public function testLogoutIsRefusedWithoutCsrfToken(): void
    {
        $client = static::createClient();
        $user = static::getContainer()->get(UserRepository::class)->findOneBy(['email' => 'admin@carstio.local']);
        $client->loginUser($user);

        // A plain GET (e.g. a prefetched link or a cross-site image tag)
        // must not log the user out: the route only accepts POST.
        $client->request('GET', '/logout');
        $this->assertSame(405, $client->getResponse()->getStatusCode());

        // A POST without the CSRF token is refused by the logout listener.
        $client->request('POST', '/logout');
        $this->assertSame(403, $client->getResponse()->getStatusCode());

        $client->request('GET', '/');
        $this->assertResponseIsSuccessful();
    }
}
