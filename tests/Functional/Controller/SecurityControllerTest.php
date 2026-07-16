<?php

namespace App\Tests\Functional\Controller;

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
}
