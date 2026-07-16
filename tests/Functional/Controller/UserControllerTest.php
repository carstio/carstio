<?php

declare(strict_types=1);

namespace App\Tests\Functional\Controller;

use App\Entity\User;
use App\Repository\UserRepository;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class UserControllerTest extends WebTestCase
{
    private function loginAs(string $email): KernelBrowser
    {
        $client = static::createClient();
        $user = static::getContainer()->get(UserRepository::class)->findOneBy(['email' => $email]);
        $this->assertNotNull($user, sprintf('Fixture user "%s" must exist.', $email));
        $client->loginUser($user);

        return $client;
    }

    private function userRepository(): UserRepository
    {
        return static::getContainer()->get(UserRepository::class);
    }

    public function testAnonymousIsRedirectedFromUserManagement(): void
    {
        $client = static::createClient();
        $client->request('GET', '/users');

        $this->assertResponseRedirects('/login');
    }

    public function testMechanicIsForbidden(): void
    {
        $client = $this->loginAs('mechanic@carstio.local');
        $client->request('GET', '/users');

        $this->assertSame(403, $client->getResponse()->getStatusCode());
    }

    public function testAdminLoadsUserIndex(): void
    {
        $client = $this->loginAs('admin@carstio.local');
        $crawler = $client->request('GET', '/users');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h1', 'Users');
        $this->assertSelectorTextContains('table', 'admin@carstio.local');
        $this->assertSelectorTextContains('table', 'mechanic@carstio.local');
    }

    public function testNavigationLinkIsHiddenForMechanic(): void
    {
        $client = $this->loginAs('mechanic@carstio.local');
        $crawler = $client->request('GET', '/');

        $this->assertResponseIsSuccessful();
        $this->assertCount(0, $crawler->filter('.app-sidebar a[href="/users"]'));
    }

    public function testNavigationLinkIsVisibleForAdmin(): void
    {
        $client = $this->loginAs('admin@carstio.local');
        $crawler = $client->request('GET', '/');

        $this->assertResponseIsSuccessful();
        $this->assertGreaterThanOrEqual(1, $crawler->filter('.app-sidebar a[href="/users"]')->count());
    }

    public function testAdminCanCreateMechanic(): void
    {
        $client = $this->loginAs('admin@carstio.local');
        $crawler = $client->request('GET', '/users/new');
        $this->assertResponseIsSuccessful();

        $form = $crawler->filter('form')->form();
        $form['user[email]'] = 'newmechanic@carstio.local';
        $form['user[fullName]'] = 'Brand New Mechanic';
        $form['user[plainPassword]'] = 'mechanic-pw-123';
        $form['user[role]'] = 'ROLE_MECHANIC';
        $form['user[isActive]']->tick();

        $client->submit($form);
        $this->assertResponseRedirects();

        $created = $this->userRepository()->findOneBy(['email' => 'newmechanic@carstio.local']);
        $this->assertNotNull($created);
        $this->assertContains('ROLE_MECHANIC', $created->getRoles());
        $this->assertNotContains('ROLE_ADMIN', $created->getRoles());
        $this->assertTrue($created->isActive());
        $this->assertNotEmpty($created->getPassword());
        $this->assertNotSame('mechanic-pw-123', $created->getPassword(), 'Password must be hashed.');
    }

    public function testAdminCanCreateAdmin(): void
    {
        $client = $this->loginAs('admin@carstio.local');
        $crawler = $client->request('GET', '/users/new');

        $form = $crawler->filter('form')->form();
        $form['user[email]'] = 'newadmin@carstio.local';
        $form['user[fullName]'] = 'Brand New Admin';
        $form['user[plainPassword]'] = 'admin-pw-12345';
        $form['user[role]'] = 'ROLE_ADMIN';

        $client->submit($form);
        $this->assertResponseRedirects();

        $created = $this->userRepository()->findOneBy(['email' => 'newadmin@carstio.local']);
        $this->assertNotNull($created);
        $this->assertContains('ROLE_ADMIN', $created->getRoles());
    }

    public function testCreateRejectsShortPassword(): void
    {
        $client = $this->loginAs('admin@carstio.local');
        $crawler = $client->request('GET', '/users/new');

        $form = $crawler->filter('form')->form();
        $form['user[email]'] = 'short@carstio.local';
        $form['user[fullName]'] = 'Short Pw';
        $form['user[plainPassword]'] = 'abc';
        $form['user[role]'] = 'ROLE_MECHANIC';

        $client->submit($form);

        $this->assertSame(422, $client->getResponse()->getStatusCode());
        $this->assertNull($this->userRepository()->findOneBy(['email' => 'short@carstio.local']));
    }

    public function testEditWithEmptyPasswordKeepsExistingHash(): void
    {
        $client = $this->loginAs('admin@carstio.local');
        $mechanic = $this->userRepository()->findOneBy(['email' => 'mechanic@carstio.local']);
        $this->assertNotNull($mechanic);
        $originalHash = $mechanic->getPassword();

        $crawler = $client->request('GET', '/users/' . $mechanic->getId() . '/edit');
        $this->assertResponseIsSuccessful();

        $form = $crawler->filter('form')->form();
        $form['user[fullName]'] = 'Mechanic Renamed';
        $form['user[plainPassword]'] = '';

        $client->submit($form);
        $this->assertResponseRedirects();

        static::getContainer()->get('doctrine')->getManager()->clear();
        $mechanic = $this->userRepository()->findOneBy(['email' => 'mechanic@carstio.local']);
        $this->assertSame('Mechanic Renamed', $mechanic->getFullName());
        $this->assertSame($originalHash, $mechanic->getPassword(), 'Empty password field must leave the hash untouched.');
    }

    public function testEditWithNewPasswordRehashes(): void
    {
        $client = $this->loginAs('admin@carstio.local');
        $mechanic = $this->userRepository()->findOneBy(['email' => 'mechanic@carstio.local']);
        $this->assertNotNull($mechanic);
        $originalHash = $mechanic->getPassword();

        $crawler = $client->request('GET', '/users/' . $mechanic->getId() . '/edit');
        $form = $crawler->filter('form')->form();
        $form['user[plainPassword]'] = 'a-new-strong-password';
        $client->submit($form);
        $this->assertResponseRedirects();

        static::getContainer()->get('doctrine')->getManager()->clear();
        $mechanic = $this->userRepository()->findOneBy(['email' => 'mechanic@carstio.local']);
        $this->assertNotSame($originalHash, $mechanic->getPassword());

        $hasher = static::getContainer()->get(UserPasswordHasherInterface::class);
        $this->assertTrue($hasher->isPasswordValid($mechanic, 'a-new-strong-password'));
    }

    public function testRoleChangePersists(): void
    {
        $client = $this->loginAs('admin@carstio.local');
        $mechanic = $this->userRepository()->findOneBy(['email' => 'mechanic@carstio.local']);

        $crawler = $client->request('GET', '/users/' . $mechanic->getId() . '/edit');
        $form = $crawler->filter('form')->form();
        $form['user[role]'] = 'ROLE_ADMIN';
        $client->submit($form);
        $this->assertResponseRedirects();

        static::getContainer()->get('doctrine')->getManager()->clear();
        $mechanic = $this->userRepository()->findOneBy(['email' => 'mechanic@carstio.local']);
        $this->assertContains('ROLE_ADMIN', $mechanic->getRoles());
    }

    public function testInvalidRoleIsRejected(): void
    {
        $client = $this->loginAs('admin@carstio.local');
        $crawler = $client->request('GET', '/users/new');

        $form = $crawler->filter('form')->form();
        // Bypass the dropdown by mutating raw form values to inject an invalid role.
        $values = $form->getPhpValues();
        $values['user']['email'] = 'bad@carstio.local';
        $values['user']['fullName'] = 'Bad Role';
        $values['user']['plainPassword'] = 'longenough123';
        $values['user']['role'] = 'ROLE_SUPERUSER';

        $client->request($form->getMethod(), $form->getUri(), $values);

        $this->assertSame(422, $client->getResponse()->getStatusCode());
        $this->assertNull($this->userRepository()->findOneBy(['email' => 'bad@carstio.local']));
    }

    public function testCannotDeactivateOwnAccount(): void
    {
        $client = $this->loginAs('admin@carstio.local');
        $admin = $this->userRepository()->findOneBy(['email' => 'admin@carstio.local']);

        // No deactivate form is rendered for the current user on their own
        // show page - the UI hides the action entirely.
        $crawler = $client->request('GET', '/users/' . $admin->getId());
        $this->assertResponseIsSuccessful();
        $this->assertCount(
            0,
            $crawler->filter('form[action="/users/' . $admin->getId() . '/deactivate"]'),
            'Self-deactivation form must not be rendered.',
        );

        // And even if the user crafts a POST manually, the controller refuses.
        $crawler = $client->request('GET', '/users/' . $admin->getId());
        $token = $crawler->filter('input[name="_token"]')->count() > 0
            ? (string) $crawler->filter('input[name="_token"]')->first()->attr('value')
            : '';
        $client->request('POST', '/users/' . $admin->getId() . '/deactivate', ['_token' => $token]);

        static::getContainer()->get('doctrine')->getManager()->clear();
        $admin = $this->userRepository()->findOneBy(['email' => 'admin@carstio.local']);
        $this->assertTrue($admin->isActive(), 'Admin must not be able to deactivate themselves.');
    }

    public function testAdminCanDeactivateAnotherUser(): void
    {
        $client = $this->loginAs('admin@carstio.local');
        $mechanic = $this->userRepository()->findOneBy(['email' => 'mechanic@carstio.local']);
        $this->assertNotNull($mechanic);

        $crawler = $client->request('GET', '/users/' . $mechanic->getId());
        $form = $crawler->filter('form[action="/users/' . $mechanic->getId() . '/deactivate"]')->form();
        $client->submit($form);
        $this->assertResponseRedirects();

        static::getContainer()->get('doctrine')->getManager()->clear();
        $mechanic = $this->userRepository()->findOneBy(['email' => 'mechanic@carstio.local']);
        $this->assertFalse($mechanic->isActive());
    }

    public function testDeactivatedUserCannotLogIn(): void
    {
        $client = static::createClient();
        $client->request('GET', '/login');
        $client->submitForm('Log in', [
            '_username' => 'former@carstio.local',
            '_password' => 'former123',
        ]);

        $this->assertResponseRedirects('/login');
        $client->followRedirect();
        $this->assertSelectorExists('.alert-danger');
    }

    public function testCanReactivateUser(): void
    {
        $client = $this->loginAs('admin@carstio.local');
        $former = $this->userRepository()->findOneBy(['email' => 'former@carstio.local']);
        $this->assertNotNull($former);
        $this->assertFalse($former->isActive());

        $crawler = $client->request('GET', '/users/' . $former->getId());
        $form = $crawler->filter('form[action="/users/' . $former->getId() . '/activate"]')->form();
        $client->submit($form);
        $this->assertResponseRedirects();

        static::getContainer()->get('doctrine')->getManager()->clear();
        $former = $this->userRepository()->findOneBy(['email' => 'former@carstio.local']);
        $this->assertTrue($former->isActive());
    }

    public function testEmailUniqueness(): void
    {
        $client = $this->loginAs('admin@carstio.local');
        $crawler = $client->request('GET', '/users/new');

        $form = $crawler->filter('form')->form();
        $form['user[email]'] = 'mechanic@carstio.local';
        $form['user[fullName]'] = 'Duplicate';
        $form['user[plainPassword]'] = 'plenty-long-pw-123';
        $form['user[role]'] = 'ROLE_MECHANIC';

        $client->submit($form);
        $this->assertSame(422, $client->getResponse()->getStatusCode());
    }
}
