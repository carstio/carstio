<?php

namespace App\Tests\Functional;

use App\Repository\UserRepository;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Guards against constraint messages leaking as raw translation keys.
 *
 * Symfony's Validator translates constraint messages through the
 * "validators" domain, so every custom `message:` key must exist in
 * translations/validators.*.yaml - keys defined only in messages.*.yaml
 * are NOT picked up and render verbatim (e.g. "user.error.email_required").
 */
class ValidationMessageTranslationTest extends WebTestCase
{
    public function testUserFormValidationErrorsRenderTranslated(): void
    {
        $client = static::createClient();
        $admin = static::getContainer()->get(UserRepository::class)->findOneBy(['email' => 'admin@carstio.local']);
        $client->loginUser($admin);

        $crawler = $client->request('GET', '/users/new');
        $form = $crawler->filter('form[name="user"]')->form();
        $form['user[email]'] = '';
        $form['user[fullName]'] = '';
        $form['user[plainPassword]'] = '';
        $client->submit($form);

        $this->assertResponseStatusCodeSame(422);
        $html = (string) $client->getResponse()->getContent();

        $this->assertStringNotContainsString('user.error.email_required', $html,
            'Raw validator translation keys must never reach the user.');
        $this->assertStringContainsString('Please enter an email address.', $html);
        $this->assertStringContainsString('Please enter a name.', $html);
        $this->assertStringContainsString('Please choose a password.', $html);
    }

    public function testDeactivatedLoginErrorRendersTranslated(): void
    {
        // The login template translates errors with the "security" domain,
        // so the UserChecker's message key must exist in security.*.yaml.
        $client = static::createClient();
        $client->request('GET', '/login');
        $client->submitForm('Log in', [
            '_username' => 'former@carstio.local',
            '_password' => 'former123',
        ]);
        $client->followRedirect();

        $html = (string) $client->getResponse()->getContent();
        $this->assertStringNotContainsString('user.error.account_inactive', $html,
            'Raw security translation keys must never reach the login page.');
        $this->assertStringContainsString('Your account is inactive.', $html);
    }

    public function testEveryConstraintMessageKeyExistsInValidatorsCatalogue(): void
    {
        // Collect every custom message key referenced by a constraint in src/.
        $keys = [];
        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator(dirname(__DIR__, 2) . '/src', \FilesystemIterator::SKIP_DOTS),
        );
        foreach ($files as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }
            $source = (string) file_get_contents($file->getPathname());
            if (preg_match_all(
                "/(?:message|maxSizeMessage|mimeTypesMessage|maxMessage|minMessage|notInRangeMessage): '([a-z0-9_.]+)'/",
                $source,
                $matches,
            )) {
                foreach ($matches[1] as $key) {
                    $keys[$key] = true;
                }
            }
        }
        $this->assertNotEmpty($keys, 'Expected to find custom constraint message keys in src/.');

        $translator = static::getContainer()->get('translator');
        foreach (['en', 'bg', 'de', 'es', 'fr'] as $locale) {
            foreach (array_keys($keys) as $key) {
                $this->assertNotSame(
                    $key,
                    $translator->trans($key, [], 'validators', $locale),
                    sprintf('Constraint message "%s" must be defined in validators.%s.yaml.', $key, $locale),
                );
            }
        }
    }
}
