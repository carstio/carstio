<?php

declare(strict_types=1);

namespace App\Tests\Functional\Controller;

use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * What happens to a session that is already open when the account behind
 * it changes: taking away someone's access must not wait for them to log
 * out by themselves.
 */
final class SessionRevocationTest extends WebTestCase
{
    private KernelBrowser $client;

    protected function setUp(): void
    {
        $this->client = static::createClient();
    }

    public function testDeactivatingAUserEndsTheirOpenSession(): void
    {
        $this->logIn('mechanic@carstio.local', 'mechanic');
        $this->client->request('GET', '/clients');
        self::assertResponseIsSuccessful();

        $this->change('mechanic@carstio.local', static fn (User $user) => $user->setIsActive(false));

        $this->client->request('GET', '/clients');
        self::assertResponseRedirects('/login');

        // And it stays ended: nothing of the old session is left to reuse.
        $this->client->request('GET', '/work-orders');
        self::assertResponseRedirects('/login');
    }

    public function testADeactivatedUserCannotChangeAnythingWithTheirOpenSession(): void
    {
        $this->logIn('mechanic@carstio.local', 'mechanic');
        $crawler = $this->client->request('GET', '/clients/new');
        self::assertResponseIsSuccessful();
        $form = $crawler->filter('form[name="client"]')->form();

        $this->change('mechanic@carstio.local', static fn (User $user) => $user->setIsActive(false));

        $form['client[name]'] = 'Created After Deactivation';
        $this->client->submit($form);
        self::assertResponseRedirects('/login');

        $count = $this->entityManager()->getConnection()->fetchOne(
            'SELECT COUNT(*) FROM client WHERE name = ?',
            ['Created After Deactivation'],
        );
        self::assertSame(0, (int) $count);
    }

    public function testReactivatingLetsTheUserLogInAgain(): void
    {
        $this->logIn('mechanic@carstio.local', 'mechanic');
        $this->change('mechanic@carstio.local', static fn (User $user) => $user->setIsActive(false));
        $this->client->request('GET', '/clients');
        self::assertResponseRedirects('/login');

        $this->change('mechanic@carstio.local', static fn (User $user) => $user->setIsActive(true));
        $this->logIn('mechanic@carstio.local', 'mechanic');
        $this->client->request('GET', '/clients');
        self::assertResponseIsSuccessful();
    }

    public function testTakingAwayTheAdministratorRoleEndsTheOpenSession(): void
    {
        $this->logIn('owner@carstio.local', null);
        $this->client->request('GET', '/settings/company');
        self::assertResponseIsSuccessful();

        $this->change('owner@carstio.local', static fn (User $user) => $user->setRoles(['ROLE_MECHANIC']));

        $this->client->request('GET', '/settings/company');
        self::assertResponseRedirects('/login');
    }

    public function testChangingThePasswordEndsOtherOpenSessions(): void
    {
        $this->logIn('mechanic@carstio.local', 'mechanic');
        $this->client->request('GET', '/clients');
        self::assertResponseIsSuccessful();

        $hasher = static::getContainer()->get(UserPasswordHasherInterface::class);
        $this->change('mechanic@carstio.local', static fn (User $user) => $user->setPassword($hasher->hashPassword($user, 'a new password')));

        $this->client->request('GET', '/clients');
        self::assertResponseRedirects('/login');
    }

    public function testChangesThatDoNotTouchAccessKeepTheSession(): void
    {
        $this->logIn('mechanic@carstio.local', 'mechanic');
        $this->client->request('GET', '/clients');
        self::assertResponseIsSuccessful();

        $this->change('mechanic@carstio.local', static fn (User $user) => $user->setFullName('Renamed Mechanic')->setPreferredLocale('en'));

        $this->client->request('GET', '/clients');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('.user-name', 'Renamed Mechanic');
    }

    /**
     * With a password: through the real login form. Without: a session
     * created directly, for accounts whose fixture password is not needed.
     */
    private function logIn(string $email, ?string $password): void
    {
        if ($password === null) {
            $this->client->loginUser($this->user($email));

            return;
        }

        $this->client->request('GET', '/login');
        $this->client->submitForm('Log in', ['_username' => $email, '_password' => $password]);
        // To the dashboard, or to the page the user was sent away from.
        self::assertResponseRedirects();
        self::assertStringEndsNotWith('/login', (string) $this->client->getResponse()->headers->get('Location'));
    }

    /**
     * @param callable(User): mixed $change
     */
    private function change(string $email, callable $change): void
    {
        $change($this->user($email));
        $this->entityManager()->flush();
    }

    private function user(string $email): User
    {
        $user = static::getContainer()->get(UserRepository::class)->findOneBy(['email' => $email]);
        self::assertInstanceOf(User::class, $user);

        return $user;
    }

    private function entityManager(): EntityManagerInterface
    {
        return static::getContainer()->get(EntityManagerInterface::class);
    }
}
