<?php

declare(strict_types=1);

namespace App\Tests\Functional\Controller;

use App\Entity\User;
use App\Repository\UserRepository;
use App\Security\PasswordReset\PasswordResetLinks;
use App\Service\Email\SmtpMailerFactory;
use App\Service\Settings\SettingsManager;
use App\Service\Settings\SettingsProvider;
use App\Settings\SettingsRegistry;
use App\Tests\Support\Email\RecordingSmtpMailerFactory;
use App\Tests\Support\Email\RecordingTransport;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mailer\Mailer;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\TransportInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\RawMessage;

/**
 * "Forgot my password": asking for a link, what the link allows, and what
 * it must not give away.
 */
final class PasswordResetTest extends WebTestCase
{
    private const NEW_PASSWORD = 'a brand new password';

    private KernelBrowser $client;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        RecordingTransport::reset();
        // The counters of the rate limiters outlive a test (and a run).
        static::getContainer()->get('cache.rate_limiter')->clear();
        $this->configureSmtp();
    }

    public function testTheLoginPageLinksToTheForm(): void
    {
        $crawler = $this->client->request('GET', '/login');
        $this->client->click($crawler->selectLink('Forgot your password?')->link());

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('form[name="password_reset_request"] input[type="email"]');
    }

    public function testTheOwnerOfAnAccountGetsALinkAndChoosesANewPassword(): void
    {
        $this->askFor('admin@carstio.local');
        self::assertResponseRedirects('/reset-password/sent');
        $this->client->followRedirect();
        self::assertSelectorTextContains('[data-password-reset-sent]', 'valid for 60 minutes');

        $email = $this->onlyEmail();
        self::assertSame('admin@carstio.local', $email->getTo()[0]->getAddress());
        self::assertSame('test-from@example.com', $email->getFrom()[0]->getAddress());
        self::assertSame('Choose a new password for Reset Test Garage', $email->getSubject());
        $link = $this->linkIn($email);
        self::assertStringContainsString('href="http://localhost' . $link . '"', (string) $email->getHtmlBody(), 'Same link in both versions of the email.');

        $crawler = $this->client->request('GET', $link);
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('.login-subtitle', 'admin@carstio.local');

        $this->client->submit($crawler->filter('form[name="new_password"]')->form([
            'new_password[plainPassword][first]' => self::NEW_PASSWORD,
            'new_password[plainPassword][second]' => self::NEW_PASSWORD,
        ]));
        self::assertResponseRedirects('/login');
        $this->client->followRedirect();
        self::assertSelectorTextContains('.alert-success', 'Your password has been changed');

        // Not logged in by the reset itself; the new password works, the old one does not.
        $this->client->request('GET', '/clients');
        self::assertResponseRedirects('/login');
        $this->logIn('admin@carstio.local', 'admin');
        self::assertResponseRedirects('/login');
        $this->logIn('admin@carstio.local', self::NEW_PASSWORD);
        self::assertResponseRedirects();
        $this->client->request('GET', '/clients');
        self::assertResponseIsSuccessful();
    }

    public function testAnAddressWithoutAnActiveAccountGetsTheSameAnswerAndNoEmail(): void
    {
        foreach (['nobody@example.com', 'former@carstio.local'] as $address) {
            $this->askFor($address);

            self::assertResponseRedirects('/reset-password/sent', null, $address);
        }

        self::assertSame([], RecordingTransport::sent());
    }

    public function testTheAddressIsFoundHoweverItIsTyped(): void
    {
        $this->askFor('  Admin@CARSTIO.local ');

        self::assertResponseRedirects('/reset-password/sent');
        self::assertSame('admin@carstio.local', $this->onlyEmail()->getTo()[0]->getAddress());
    }

    public function testTheEmailIsWrittenInTheLanguageOfItsRecipient(): void
    {
        // The visitor's page is in English; the mechanic works in Bulgarian.
        $this->askFor('mechanic@carstio.local');

        $email = $this->onlyEmail();
        self::assertSame('Изберете нова парола за Reset Test Garage', $email->getSubject());
        self::assertStringContainsString('Връзката е валидна 60 минути', (string) $email->getTextBody());
        self::assertStringContainsString('lang="bg"', (string) $email->getHtmlBody());

        // ...and the page that follows is still in the visitor's language.
        $this->client->followRedirect();
        self::assertSelectorTextContains('.login-title', 'Check your email');
    }

    public function testALinkWorksOnlyOnce(): void
    {
        $link = $this->linkFor('admin@carstio.local');
        $this->choosePassword($link, self::NEW_PASSWORD);
        self::assertResponseRedirects('/login');

        $this->client->request('GET', $link);
        $this->assertLinkRefused();

        $this->client->request('POST', $link, ['new_password' => ['plainPassword' => ['first' => 'another password', 'second' => 'another password']]]);
        $this->assertLinkRefused();
        $this->assertPasswordIs('admin@carstio.local', self::NEW_PASSWORD);
    }

    public function testEarlierLinksStopWorkingOnceOneIsUsed(): void
    {
        $first = $this->linkFor('admin@carstio.local', new \DateTimeImmutable('+30 minutes'));
        $second = $this->linkFor('admin@carstio.local');

        $this->choosePassword($second, self::NEW_PASSWORD);
        self::assertResponseRedirects('/login');

        $this->client->request('GET', $first);
        $this->assertLinkRefused();
    }

    public function testAnExpiredLinkIsRefused(): void
    {
        $link = $this->linkFor('admin@carstio.local', new \DateTimeImmutable('-1 second'));

        $this->client->request('GET', $link);
        $this->assertLinkRefused();

        $this->client->request('POST', $link, ['new_password' => ['plainPassword' => ['first' => self::NEW_PASSWORD, 'second' => self::NEW_PASSWORD]]]);
        $this->assertLinkRefused();
        $this->assertPasswordIs('admin@carstio.local', 'admin');
    }

    public function testAnAlteredLinkIsRefused(): void
    {
        $link = $this->linkFor('mechanic@carstio.local', new \DateTimeImmutable('+10 minutes'));
        self::assertSame(1, preg_match('#^(.*/reset-password)/(\d+)/(\d+)/(.+)$#', $link, $parts));
        [, $base, $id, $expires, $signature] = $parts;
        $adminId = (string) $this->user('admin@carstio.local')->getId();

        $altered = [
            'for another account' => [$adminId, $expires, $signature],
            'valid for longer' => [$id, (string) ((int) $expires + 3600), $signature],
            'different signature' => [$id, $expires, strrev($signature)],
            'signature of another link' => [$id, $expires, basename($this->linkFor('mechanic@carstio.local'))],
        ];
        foreach ($altered as $what => $segments) {
            $this->client->request('GET', $base . '/' . implode('/', $segments));
            $this->assertLinkRefused($what);
        }

        // The untouched link is fine.
        $this->client->request('GET', $link);
        self::assertResponseIsSuccessful();
    }

    public function testALinkStopsWorkingWhenTheAccountChanges(): void
    {
        $deactivated = $this->linkFor('mechanic@carstio.local');
        $this->change('mechanic@carstio.local', static fn (User $user) => $user->setIsActive(false));
        $this->client->request('GET', $deactivated);
        $this->assertLinkRefused('deactivated');

        $renamed = $this->linkFor('owner@carstio.local');
        $this->change('owner@carstio.local', static fn (User $user) => $user->setEmail('someone-else@carstio.local'));
        $this->client->request('GET', $renamed);
        $this->assertLinkRefused('email changed');
    }

    public function testTheNewPasswordHasToBeLongEnoughAndTypedTheSameTwice(): void
    {
        $link = $this->linkFor('admin@carstio.local');

        $this->choosePassword($link, 'short');
        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('.invalid-feedback', 'at least 8 characters');

        $this->choosePassword($link, self::NEW_PASSWORD, 'something different');
        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('.invalid-feedback', 'The two passwords do not match.');

        $this->choosePassword($link, '');
        self::assertResponseStatusCodeSame(422);

        $this->assertPasswordIs('admin@carstio.local', 'admin');

        // The link was not used up by the failed attempts.
        $this->choosePassword($link, self::NEW_PASSWORD);
        self::assertResponseRedirects('/login');
    }

    public function testANewPasswordEndsTheSessionsThatWereOpen(): void
    {
        $this->logIn('mechanic@carstio.local', 'mechanic');
        $this->client->request('GET', '/clients');
        self::assertResponseIsSuccessful();
        $session = $this->client->getCookieJar()->all();

        // Someone else (no session) uses the link from the email.
        $this->client->getCookieJar()->clear();
        $this->choosePassword($this->linkFor('mechanic@carstio.local'), self::NEW_PASSWORD);
        self::assertResponseRedirects('/login');

        $this->client->getCookieJar()->clear();
        foreach ($session as $cookie) {
            $this->client->getCookieJar()->set($cookie);
        }
        $this->client->request('GET', '/clients');
        self::assertResponseRedirects('/login');
    }

    public function testSomeoneLoggedInIsSentToTheDashboard(): void
    {
        $link = $this->linkFor('mechanic@carstio.local');
        $this->client->loginUser($this->user('admin@carstio.local'));

        foreach (['/reset-password', '/reset-password/sent', $link] as $url) {
            $this->client->request('GET', $url);
            self::assertResponseRedirects('/', null, $url);
        }
    }

    public function testWithoutEmailSettingsThePageSaysSoInsteadOfPretending(): void
    {
        $this->clearSmtp();

        $this->client->request('GET', '/reset-password');
        self::assertResponseIsSuccessful();
        self::assertSelectorExists('[data-password-reset-unavailable]');
        self::assertSelectorNotExists('form[name="password_reset_request"]');

        $this->client->request('POST', '/reset-password', ['password_reset_request' => ['email' => 'admin@carstio.local']]);
        self::assertSelectorExists('[data-password-reset-unavailable]');
        self::assertSame([], RecordingTransport::sent());
    }

    public function testAMailServerThatFailsLooksTheSameFromOutside(): void
    {
        // One kernel for the whole test, so the replaced service stays.
        $this->client->disableReboot();
        $failing = new class(static::getContainer()->get(SettingsProvider::class)) extends RecordingSmtpMailerFactory {
            public function create(): MailerInterface
            {
                return new Mailer(new class implements TransportInterface {
                    public function send(RawMessage $message, ?Envelope $envelope = null): ?SentMessage
                    {
                        throw new TransportException('Connection refused');
                    }

                    public function __toString(): string
                    {
                        return 'failing://test';
                    }
                });
            }
        };
        static::getContainer()->set(SmtpMailerFactory::class, $failing);

        $this->askFor('admin@carstio.local');

        self::assertResponseRedirects('/reset-password/sent');
    }

    public function testOneAddressCanBeAskedForOnlyAFewTimesAnHour(): void
    {
        for ($attempt = 1; $attempt <= 3; ++$attempt) {
            $this->askFor('admin@carstio.local');
            self::assertResponseRedirects('/reset-password/sent', null, 'attempt ' . $attempt);
        }

        $this->askFor('Admin@Carstio.Local');
        self::assertResponseStatusCodeSame(429);
        self::assertSelectorTextContains('.alert-danger', 'Too many attempts');
        self::assertCount(3, RecordingTransport::sent());

        // Other addresses are not affected...
        $this->askFor('mechanic@carstio.local');
        self::assertResponseRedirects('/reset-password/sent');

        // ...and an address without an account is limited in the same way.
        for ($attempt = 1; $attempt <= 3; ++$attempt) {
            $this->askFor('nobody@example.com');
            self::assertResponseRedirects('/reset-password/sent');
        }
        $this->askFor('nobody@example.com');
        self::assertResponseStatusCodeSame(429);
    }

    public function testOneVisitorCanAskOnlySoOften(): void
    {
        for ($attempt = 1; $attempt <= 10; ++$attempt) {
            $this->askFor(sprintf('person%d@example.com', $attempt));
            self::assertResponseRedirects('/reset-password/sent', null, 'attempt ' . $attempt);
        }

        $this->askFor('admin@carstio.local');
        self::assertResponseStatusCodeSame(429);
        self::assertSame([], RecordingTransport::sent());

        // Someone else is not held back by it.
        $this->askFor('admin@carstio.local', '203.0.113.7');
        self::assertResponseRedirects('/reset-password/sent');
        self::assertCount(1, RecordingTransport::sent());
    }

    private function askFor(string $address, ?string $ip = null): void
    {
        $crawler = $this->client->request('GET', '/reset-password');
        self::assertResponseIsSuccessful();

        $form = $crawler->filter('form[name="password_reset_request"]')->form();
        $this->client->request(
            $form->getMethod(),
            $form->getUri(),
            array_replace_recursive($form->getPhpValues(), ['password_reset_request' => ['email' => $address]]),
            [],
            $ip === null ? [] : ['REMOTE_ADDR' => $ip],
        );
    }

    private function choosePassword(string $link, string $password, ?string $repeated = null): void
    {
        $crawler = $this->client->request('GET', $link);
        self::assertResponseIsSuccessful();

        $this->client->submit($crawler->filter('form[name="new_password"]')->form([
            'new_password[plainPassword][first]' => $password,
            'new_password[plainPassword][second]' => $repeated ?? $password,
        ]));
    }

    private function assertLinkRefused(string $what = ''): void
    {
        self::assertResponseRedirects('/reset-password', null, $what);
        $this->client->followRedirect();
        self::assertSelectorTextContains('.alert-danger', 'This link cannot be used any more');
    }

    private function assertPasswordIs(string $email, string $password): void
    {
        $this->client->getCookieJar()->clear();
        $this->logIn($email, $password);
        self::assertResponseRedirects();
        self::assertStringEndsNotWith('/login', (string) $this->client->getResponse()->headers->get('Location'));
        $this->client->getCookieJar()->clear();
    }

    private function logIn(string $email, string $password): void
    {
        $this->client->request('GET', '/login');
        $this->client->submitForm('Log in', ['_username' => $email, '_password' => $password]);
    }

    private function linkFor(string $email, ?\DateTimeInterface $expiresAt = null): string
    {
        // Relative, the way the test client asks for pages.
        $url = static::getContainer()->get(PasswordResetLinks::class)->createUrl($this->user($email), $expiresAt);

        return (string) preg_replace('#^https?://[^/]+#', '', $url);
    }

    private function linkIn(Email $email): string
    {
        self::assertSame(1, preg_match('#http://localhost(/reset-password/\d+/\d+/[A-Za-z0-9_~-]+)#', (string) $email->getTextBody(), $match), (string) $email->getTextBody());

        return $match[1];
    }

    private function onlyEmail(): Email
    {
        $sent = RecordingTransport::sent();
        self::assertCount(1, $sent);
        self::assertInstanceOf(Email::class, $sent[0]);

        return $sent[0];
    }

    /**
     * @param callable(User): mixed $change
     */
    private function change(string $email, callable $change): void
    {
        $change($this->user($email));
        static::getContainer()->get(EntityManagerInterface::class)->flush();
    }

    private function user(string $email): User
    {
        $user = static::getContainer()->get(UserRepository::class)->findOneBy(['email' => $email]);
        self::assertInstanceOf(User::class, $user);

        return $user;
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
        $manager->setMany(SettingsRegistry::SECTION_COMPANY, [
            SettingsRegistry::KEY_COMPANY_NAME => 'Reset Test Garage',
        ]);
    }

    private function clearSmtp(): void
    {
        static::getContainer()->get(SettingsManager::class)->setMany(SettingsRegistry::SECTION_EMAIL, [
            SettingsRegistry::KEY_SMTP_HOST => '',
            SettingsRegistry::KEY_SMTP_PORT => '',
            SettingsRegistry::KEY_SMTP_FROM_EMAIL => '',
            SettingsRegistry::KEY_SMTP_FROM_NAME => '',
        ]);
    }
}
