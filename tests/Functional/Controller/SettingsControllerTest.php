<?php

namespace App\Tests\Functional\Controller;

use App\Repository\Settings\SettingRepository;
use App\Repository\UserRepository;
use App\Service\Settings\SettingsManager;
use App\Service\Settings\SettingsProvider;
use App\Service\Settings\SmtpPasswordEncrypter;
use App\Settings\SettingsRegistry;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class SettingsControllerTest extends WebTestCase
{
    private function loginAdmin(): KernelBrowser
    {
        $client = static::createClient();
        $user = static::getContainer()->get(UserRepository::class)->findOneBy(['email' => 'admin@carstio.local']);
        $client->loginUser($user);

        return $client;
    }

    private function loginMechanic(): KernelBrowser
    {
        $client = static::createClient();
        $user = static::getContainer()->get(UserRepository::class)->findOneBy(['email' => 'mechanic@carstio.local']);
        self::assertNotNull($user, 'Mechanic fixture user must exist for security tests.');
        $client->loginUser($user);

        return $client;
    }

    private function provider(): SettingsProvider
    {
        // Drop the request-scoped cache so each assertion sees fresh DB state.
        $provider = static::getContainer()->get(SettingsProvider::class);
        $provider->clearCache();

        return $provider;
    }

    public function testSettingsRequiresAuthentication(): void
    {
        $client = static::createClient();
        $client->request('GET', '/settings/company');

        $this->assertResponseRedirects();
    }

    public function testSettingsRequiresAdminRole(): void
    {
        $client = $this->loginMechanic();
        $client->request('GET', '/settings/company');

        $this->assertSame(403, $client->getResponse()->getStatusCode());
    }

    public function testSettingsIndexRedirectsToCompany(): void
    {
        $client = $this->loginAdmin();
        $client->request('GET', '/settings');

        $this->assertResponseRedirects('/settings/company');
    }

    public function testCompanyPageLoadsWithExistingValues(): void
    {
        $client = $this->loginAdmin();

        // Pin the value so we don't depend on whatever a sibling test
        // happened to leave in the singleton settings row.
        static::getContainer()->get(SettingsManager::class)->set(
            SettingsRegistry::SECTION_COMPANY,
            SettingsRegistry::KEY_COMPANY_NAME,
            'Carstio Workshop Demo',
        );

        $crawler = $client->request('GET', '/settings/company');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorExists('form');
        $input = $crawler->filter('input[name="settings_section[company_name]"]');
        $this->assertCount(1, $input);
        $this->assertSame('Carstio Workshop Demo', $input->attr('value'));

        // Sub-nav links every section.
        $this->assertSelectorExists('a[href="/settings/regional"]');
        $this->assertSelectorExists('a[href="/settings/currency-tax"]');
        $this->assertSelectorExists('a[href="/settings/documents"]');
        $this->assertSelectorExists('a[href="/settings/email"]');
    }

    public function testCompanyValuesPersistThroughGenericStore(): void
    {
        $client = $this->loginAdmin();
        $crawler = $client->request('GET', '/settings/company');

        $form = $crawler->selectButton('Save')->form();
        $form['settings_section[company_name]'] = 'Acme Garage Ltd.';
        $form['settings_section[company_id]'] = '900112233';
        $form['settings_section[vat_number]'] = 'BG900112233';
        $form['settings_section[email]'] = 'hello@acme.test';
        $form['settings_section[website]'] = 'https://acme.test';

        $client->submit($form);
        $this->assertResponseRedirects('/settings/company');

        $provider = $this->provider();
        $this->assertSame('Acme Garage Ltd.', $provider->getString(SettingsRegistry::SECTION_COMPANY, SettingsRegistry::KEY_COMPANY_NAME));
        $this->assertSame('BG900112233', $provider->getString(SettingsRegistry::SECTION_COMPANY, SettingsRegistry::KEY_VAT_NUMBER));
    }

    public function testOptionalCompanyFieldsCanBeBlank(): void
    {
        $client = $this->loginAdmin();
        $crawler = $client->request('GET', '/settings/company');

        $form = $crawler->selectButton('Save')->form();
        $form['settings_section[company_name]'] = 'Lean & Mean Ltd.';
        $form['settings_section[company_id]'] = '';
        $form['settings_section[vat_number]'] = '';
        $form['settings_section[address]'] = '';
        $form['settings_section[website]'] = '';

        $client->submit($form);
        $this->assertResponseRedirects('/settings/company');

        $provider = $this->provider();
        $this->assertNull($provider->getString(SettingsRegistry::SECTION_COMPANY, SettingsRegistry::KEY_COMPANY_ID));
    }

    public function testInvalidEmailShowsValidationError(): void
    {
        $client = $this->loginAdmin();
        $crawler = $client->request('GET', '/settings/company');

        $form = $crawler->selectButton('Save')->form();
        $form['settings_section[company_name]'] = '';
        $form['settings_section[email]'] = 'not-an-email';

        $client->submit($form);

        $this->assertResponseStatusCodeSame(422);
        $this->assertSelectorExists('.invalid-feedback');
    }

    public function testCurrencyTaxPageSaves(): void
    {
        $client = $this->loginAdmin();
        $crawler = $client->request('GET', '/settings/currency-tax');

        $this->assertResponseIsSuccessful();
        $form = $crawler->selectButton('Save')->form();
        $form['settings_section[default_currency_code]'] = 'EUR';
        $form['settings_section[default_tax_rate]'] = '20.50';

        $client->submit($form);
        $this->assertResponseRedirects('/settings/currency-tax');

        $provider = $this->provider();
        $this->assertSame('EUR', $provider->getString(SettingsRegistry::SECTION_CURRENCY, SettingsRegistry::KEY_DEFAULT_CURRENCY_CODE));
        $this->assertSame(20.50, $provider->getFloat(SettingsRegistry::SECTION_CURRENCY, SettingsRegistry::KEY_DEFAULT_TAX_RATE));
    }

    public function testDefaultTaxRateFallsBackToNullWhenCleared(): void
    {
        $client = $this->loginAdmin();
        $crawler = $client->request('GET', '/settings/currency-tax');
        $form = $crawler->selectButton('Save')->form();
        $form['settings_section[default_tax_rate]'] = '';
        $client->submit($form);

        $this->assertResponseRedirects('/settings/currency-tax');
        $this->assertNull(
            $this->provider()->getFloat(SettingsRegistry::SECTION_CURRENCY, SettingsRegistry::KEY_DEFAULT_TAX_RATE),
        );
    }

    public function testRegionalPageSavesAndCastsValues(): void
    {
        $client = $this->loginAdmin();
        $crawler = $client->request('GET', '/settings/regional');

        $form = $crawler->selectButton('Save')->form();
        $form['settings_section[default_locale]'] = 'bg';
        $form['settings_section[timezone]'] = 'Europe/Berlin';
        $form['settings_section[date_format]'] = 'd.m.Y';
        $form['settings_section[time_format]'] = 'H:i';

        try {
            $client->submit($form);
            $this->assertResponseRedirects('/settings/regional');

            $provider = $this->provider();
            $this->assertSame('bg', $provider->getString(SettingsRegistry::SECTION_REGIONAL, SettingsRegistry::KEY_DEFAULT_LOCALE));
            $this->assertSame('Europe/Berlin', $provider->getString(SettingsRegistry::SECTION_REGIONAL, SettingsRegistry::KEY_TIMEZONE));
        } finally {
            // Restore default_locale so sibling tests don't render in
            // Bulgarian via the global settings fallback.
            static::getContainer()->get(SettingsManager::class)->set(
                SettingsRegistry::SECTION_REGIONAL,
                SettingsRegistry::KEY_DEFAULT_LOCALE,
                'en',
            );
            static::getContainer()->get(SettingsManager::class)->set(
                SettingsRegistry::SECTION_REGIONAL,
                SettingsRegistry::KEY_TIMEZONE,
                'Europe/Sofia',
            );
        }
    }

    public function testRegionalTimezoneIsRenderedAsSelect(): void
    {
        $client = $this->loginAdmin();
        $crawler = $client->request('GET', '/settings/regional');

        $this->assertResponseIsSuccessful();
        $select = $crawler->filter('select[name="settings_section[timezone]"]');
        $this->assertCount(1, $select, 'Timezone field must be a <select>, not a text input.');
        $this->assertCount(0, $crawler->filter('input[name="settings_section[timezone]"]'));

        // Sanity-check a few well-known IANA identifiers are exposed as options.
        $optionValues = $select->filter('option')->each(fn ($node) => $node->attr('value'));
        $this->assertContains('Europe/Sofia', $optionValues);
        $this->assertContains('Europe/Berlin', $optionValues);
        $this->assertContains('America/New_York', $optionValues);
        $this->assertContains('UTC', $optionValues);
    }

    public function testRegionalTimezoneSelectMarksStoredValueAsSelected(): void
    {
        $client = $this->loginAdmin();
        $manager = static::getContainer()->get(SettingsManager::class);
        $manager->set(SettingsRegistry::SECTION_REGIONAL, SettingsRegistry::KEY_TIMEZONE, 'America/New_York');

        try {
            $crawler = $client->request('GET', '/settings/regional');

            $selected = $crawler->filter('select[name="settings_section[timezone]"] option[selected]');
            $this->assertCount(1, $selected);
            $this->assertSame('America/New_York', $selected->attr('value'));
        } finally {
            $manager->set(SettingsRegistry::SECTION_REGIONAL, SettingsRegistry::KEY_TIMEZONE, 'Europe/Sofia');
        }
    }

    public function testInvalidTimezoneIsRejected(): void
    {
        $client = $this->loginAdmin();
        $manager = static::getContainer()->get(SettingsManager::class);
        $manager->set(SettingsRegistry::SECTION_REGIONAL, SettingsRegistry::KEY_TIMEZONE, 'Europe/Sofia');

        // Bypass BrowserKit's <select> validation by posting raw form data -
        // we still want the server to reject unknown identifiers.
        $client->request('POST', '/settings/regional', [
            'settings_section' => [
                'default_locale' => 'en',
                'timezone' => 'Not/A_Real_Zone',
                'date_format' => 'Y-m-d',
                'time_format' => 'H:i',
            ],
        ]);

        $this->assertResponseStatusCodeSame(422);
        $this->assertSame(
            'Europe/Sofia',
            $this->provider()->getString(SettingsRegistry::SECTION_REGIONAL, SettingsRegistry::KEY_TIMEZONE),
            'A rejected submission must not overwrite the stored timezone.',
        );
    }

    public function testRegionalPageRendersWhenStoredTimezoneIsInvalid(): void
    {
        $client = $this->loginAdmin();

        // Simulate legacy data: a row with an identifier PHP no longer knows
        // about. The page must still render with the dropdown defaulted.
        $repo = static::getContainer()->get(SettingRepository::class);
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $row = $repo->findOneByKey(SettingsRegistry::SECTION_REGIONAL, SettingsRegistry::KEY_TIMEZONE);
        self::assertNotNull($row);
        $original = $row->getValue();
        $row->setValue('Atlantis/Hidden_City');
        $em->flush();

        try {
            $client->request('GET', '/settings/regional');

            $this->assertResponseIsSuccessful();
        } finally {
            $row->setValue($original);
            $em->flush();
        }
    }

    public function testDocumentsSectionSavesIntegerCastValue(): void
    {
        $client = $this->loginAdmin();
        $crawler = $client->request('GET', '/settings/documents');

        $form = $crawler->selectButton('Save')->form();
        $form['settings_section[offer_number_prefix]'] = 'OFF';
        $form['settings_section[work_order_number_prefix]'] = 'WO';
        $form['settings_section[invoice_number_prefix]'] = 'INV';
        $form['settings_section[default_offer_validity_days]'] = '30';
        $form['settings_section[document_footer_text]'] = 'Thank you.';

        $client->submit($form);
        $this->assertResponseRedirects('/settings/documents');

        $provider = $this->provider();
        $this->assertSame('OFF', $provider->getString(SettingsRegistry::SECTION_DOCUMENTS, SettingsRegistry::KEY_OFFER_NUMBER_PREFIX));
        $this->assertSame(30, $provider->getInt(SettingsRegistry::SECTION_DOCUMENTS, SettingsRegistry::KEY_DEFAULT_OFFER_VALIDITY_DAYS));
        $this->assertSame('Thank you.', $provider->getString(SettingsRegistry::SECTION_DOCUMENTS, SettingsRegistry::KEY_DOCUMENT_FOOTER_TEXT));
    }

    public function testNegativeOfferValidityShowsValidationError(): void
    {
        $client = $this->loginAdmin();
        $crawler = $client->request('GET', '/settings/documents');

        $form = $crawler->selectButton('Save')->form();
        $form['settings_section[default_offer_validity_days]'] = '-5';
        $client->submit($form);

        $this->assertResponseStatusCodeSame(422);
    }

    public function testEmailSettingsCanBeSavedWithoutPassword(): void
    {
        $client = $this->loginAdmin();

        // Reset any previously-stored ciphertext so the assertion below
        // doesn't see leftover state from another test.
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $repo = static::getContainer()->get(SettingRepository::class);
        $existing = $repo->findOneByKey(SettingsRegistry::SECTION_EMAIL, SettingsRegistry::KEY_SMTP_PASSWORD);
        if ($existing !== null) {
            $existing->setValue(null);
            $em->flush();
        }

        $crawler = $client->request('GET', '/settings/email');
        $form = $crawler->selectButton('Save')->form();
        $form['settings_section[smtp_host]'] = 'mail.example.com';
        $form['settings_section[smtp_port]'] = '587';
        $form['settings_section[smtp_encryption]'] = 'tls';
        $form['settings_section[smtp_username]'] = 'apikey';
        $form['settings_section[smtp_from_email]'] = 'no-reply@example.com';

        $client->submit($form);
        $this->assertResponseRedirects('/settings/email');

        $provider = $this->provider();
        $this->assertSame('mail.example.com', $provider->getString(SettingsRegistry::SECTION_EMAIL, SettingsRegistry::KEY_SMTP_HOST));
        $this->assertSame(587, $provider->getInt(SettingsRegistry::SECTION_EMAIL, SettingsRegistry::KEY_SMTP_PORT));
        $this->assertFalse($provider->isSet(SettingsRegistry::SECTION_EMAIL, SettingsRegistry::KEY_SMTP_PASSWORD));
    }

    public function testEmailPasswordIsStoredEncrypted(): void
    {
        $client = $this->loginAdmin();
        $crawler = $client->request('GET', '/settings/email');

        $form = $crawler->selectButton('Save')->form();
        $form['settings_section[smtp_host]'] = 'mail.example.com';
        $form['settings_section[smtp_port]'] = '587';
        $form['settings_section[smtp_encryption]'] = 'tls';
        $form['settings_section[newPassword]'] = 'super-secret';

        $client->submit($form);
        $this->assertResponseRedirects('/settings/email');

        $repo = static::getContainer()->get(SettingRepository::class);
        $row = $repo->findOneByKey(SettingsRegistry::SECTION_EMAIL, SettingsRegistry::KEY_SMTP_PASSWORD);
        $this->assertNotNull($row);
        $this->assertNotNull($row->getValue());
        $this->assertStringStartsWith('enc1:', $row->getValue());
        $this->assertStringNotContainsString('super-secret', $row->getValue());

        $encrypter = static::getContainer()->get(SmtpPasswordEncrypter::class);
        $this->assertSame('super-secret', $encrypter->decrypt($row->getValue()));
    }

    public function testEmptyPasswordPreservesStoredCiphertext(): void
    {
        $client = $this->loginAdmin();

        $crawler = $client->request('GET', '/settings/email');
        $form = $crawler->selectButton('Save')->form();
        $form['settings_section[smtp_host]'] = 'mail.example.com';
        $form['settings_section[smtp_port]'] = '587';
        $form['settings_section[smtp_encryption]'] = 'tls';
        $form['settings_section[newPassword]'] = 'first-secret';
        $client->submit($form);

        $repo = static::getContainer()->get(SettingRepository::class);
        $original = $repo->findOneByKey(SettingsRegistry::SECTION_EMAIL, SettingsRegistry::KEY_SMTP_PASSWORD)?->getValue();
        $this->assertNotNull($original);

        $crawler = $client->request('GET', '/settings/email');
        $form = $crawler->selectButton('Save')->form();
        $form['settings_section[smtp_host]'] = 'mail.example.com';
        $form['settings_section[smtp_port]'] = '465';
        $form['settings_section[smtp_encryption]'] = 'ssl';
        // newPassword left empty.
        $client->submit($form);

        $em = static::getContainer()->get(EntityManagerInterface::class);
        $em->clear();
        $row = $repo->findOneByKey(SettingsRegistry::SECTION_EMAIL, SettingsRegistry::KEY_SMTP_PASSWORD);
        $this->assertSame($original, $row?->getValue());
    }

    public function testInvalidSmtpPortShowsValidationError(): void
    {
        $client = $this->loginAdmin();
        $crawler = $client->request('GET', '/settings/email');

        $form = $crawler->selectButton('Save')->form();
        $form['settings_section[smtp_port]'] = '99999';
        $client->submit($form);

        $this->assertResponseStatusCodeSame(422);
        $this->assertSelectorExists('.invalid-feedback');
    }

    public function testDefaultsAppliedWhenSettingHasNoRow(): void
    {
        $this->loginAdmin();

        // Wipe any existing offer-validity row, then verify the provider
        // returns the registry default rather than null.
        $repo = static::getContainer()->get(SettingRepository::class);
        $em = static::getContainer()->get(EntityManagerInterface::class);

        $existing = $repo->findOneByKey(SettingsRegistry::SECTION_DOCUMENTS, SettingsRegistry::KEY_DEFAULT_OFFER_VALIDITY_DAYS);
        if ($existing !== null) {
            $em->remove($existing);
            $em->flush();
        }

        $this->assertSame(14, $this->provider()->getInt(SettingsRegistry::SECTION_DOCUMENTS, SettingsRegistry::KEY_DEFAULT_OFFER_VALIDITY_DAYS));
    }

    public function testLogoUploadAndRemoveFlow(): void
    {
        $client = $this->loginAdmin();

        $tmpPath = sys_get_temp_dir() . '/test-logo-' . uniqid() . '.png';
        $img = imagecreatetruecolor(32, 32);
        imagepng($img, $tmpPath);
        imagedestroy($img);

        $crawler = $client->request('GET', '/settings/company');
        $form = $crawler->selectButton('Save')->form();
        $form['settings_section[company_name]'] = 'Logo Co';
        $form['settings_section[logoFile]']->upload($tmpPath);

        $client->submit($form);
        $this->assertResponseRedirects('/settings/company');

        $logoPath = $this->provider()->getString(SettingsRegistry::SECTION_COMPANY, SettingsRegistry::KEY_LOGO_PATH);
        $this->assertNotNull($logoPath);
        $projectDir = static::getContainer()->getParameter('kernel.project_dir');
        $absolute = $projectDir . '/public' . $logoPath;
        $this->assertFileExists($absolute);

        $crawler = $client->followRedirect();
        $removeForm = $crawler->filter('#company-logo-remove-form')->form();
        $client->submit($removeForm);
        $this->assertResponseRedirects('/settings/company');

        $this->assertNull($this->provider()->getString(SettingsRegistry::SECTION_COMPANY, SettingsRegistry::KEY_LOGO_PATH));
        $this->assertFileDoesNotExist($absolute);

        if (file_exists($tmpPath)) {
            @unlink($tmpPath);
        }
    }

    public function testFixturesSeedExpectedSections(): void
    {
        $this->loginAdmin();

        $sections = ['company', 'regional', 'currency', 'documents', 'email'];
        foreach ($sections as $section) {
            $rows = static::getContainer()->get(SettingRepository::class)->findBySection($section);
            $this->assertNotEmpty($rows, sprintf('Fixtures should seed at least one row for section "%s".', $section));
        }
    }
}
