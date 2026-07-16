<?php

namespace App\Controller;

use App\Entity\Settings\RegionalDefaults;
use App\Enum\MileageUnit;
use App\Form\Settings\SettingsSectionType;
use App\Service\Settings\LogoUploader;
use App\Service\Settings\SettingsManager;
use App\Service\Settings\SettingsProvider;
use App\Service\Settings\TimezoneChoiceProvider;
use App\Settings\SettingsRegistry;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Validator\Constraints\File;

/**
 * Settings UI. Each route renders a section template using the same
 * dynamic form type ({@see SettingsSectionType}) plus any section-specific
 * extras (logo upload, SMTP password input).
 */
#[Route('/settings')]
#[IsGranted('ROLE_ADMIN')]
final class SettingsController extends AbstractController
{
    #[Route('', name: 'app_settings_index', methods: ['GET'])]
    public function index(): Response
    {
        return $this->redirectToRoute('app_settings_company');
    }

    #[Route('/company', name: 'app_settings_company', methods: ['GET', 'POST'])]
    public function company(
        Request $request,
        SettingsProvider $provider,
        SettingsManager $manager,
        LogoUploader $logoUploader,
    ): Response {
        $section = SettingsRegistry::SECTION_COMPANY;
        $values = $provider->valuesForSection($section);
        unset($values[SettingsRegistry::KEY_LOGO_PATH]);

        $form = $this->createSectionForm($section, $values);
        $form->add('logoFile', FileType::class, [
            'label' => 'settings.company.field.logo',
            'help' => 'settings.company.help.logo',
            'mapped' => false,
            'required' => false,
            'constraints' => [
                new File(
                    maxSize: '2M',
                    mimeTypes: ['image/png', 'image/jpeg', 'image/webp', 'image/svg+xml'],
                    maxSizeMessage: 'settings.company.error.logo_too_large',
                    mimeTypesMessage: 'settings.company.error.logo_mime_type',
                ),
            ],
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $logoFile = $form->get('logoFile')->getData();
            if ($logoFile instanceof UploadedFile) {
                $logoUploader->remove($provider->getString($section, SettingsRegistry::KEY_LOGO_PATH));
                $manager->setFilePath($section, SettingsRegistry::KEY_LOGO_PATH, $logoUploader->upload($logoFile));
            }

            $manager->setMany($section, $form->getData());
            $this->addFlash('success', 'flash.settings.company.saved');

            return $this->redirectToRoute('app_settings_company');
        }

        return $this->render('settings/company.html.twig', [
            'form' => $form,
            'logo_path' => $provider->getString($section, SettingsRegistry::KEY_LOGO_PATH),
            'company_name' => $provider->getString($section, SettingsRegistry::KEY_COMPANY_NAME),
            'active_section' => 'company',
        ]);
    }

    #[Route('/company/logo/remove', name: 'app_settings_company_logo_remove', methods: ['POST'])]
    public function removeCompanyLogo(
        Request $request,
        SettingsProvider $provider,
        SettingsManager $manager,
        LogoUploader $logoUploader,
    ): Response {
        if (!$this->isCsrfTokenValid('remove-company-logo', $request->request->get('_token'))) {
            return $this->redirectToRoute('app_settings_company');
        }

        $current = $provider->getString(SettingsRegistry::SECTION_COMPANY, SettingsRegistry::KEY_LOGO_PATH);
        if ($current !== null && $current !== '') {
            $logoUploader->remove($current);
            $manager->setFilePath(SettingsRegistry::SECTION_COMPANY, SettingsRegistry::KEY_LOGO_PATH, null);
            $this->addFlash('success', 'flash.settings.company.logo_removed');
        }

        return $this->redirectToRoute('app_settings_company');
    }

    #[Route('/regional', name: 'app_settings_regional', methods: ['GET', 'POST'])]
    public function regional(
        Request $request,
        SettingsProvider $provider,
        SettingsManager $manager,
        TimezoneChoiceProvider $timezoneChoices,
    ): Response {
        $section = SettingsRegistry::SECTION_REGIONAL;
        $values = $provider->valuesForSection($section);

        // Old data may store an identifier that PHP no longer knows about
        // (deprecated/renamed zones). Fall back to the default so the
        // dropdown renders without pre-selecting a phantom option.
        if (!$timezoneChoices->isValid($values[SettingsRegistry::KEY_TIMEZONE] ?? null)) {
            $values[SettingsRegistry::KEY_TIMEZONE] = RegionalDefaults::TIMEZONE;
        }

        // Same defensive treatment for the mileage unit: an unknown stored
        // value would otherwise leave the <select> with no pre-selected option.
        $storedUnit = $values[SettingsRegistry::KEY_MILEAGE_UNIT] ?? null;
        if ($storedUnit === null || MileageUnit::tryFrom((string) $storedUnit) === null) {
            $values[SettingsRegistry::KEY_MILEAGE_UNIT] = RegionalDefaults::MILEAGE_UNIT;
        }

        $form = $this->createSectionForm($section, $values);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $manager->setMany($section, $form->getData());
            $this->addFlash('success', 'flash.settings.regional.saved');

            return $this->redirectToRoute('app_settings_regional');
        }

        return $this->render('settings/regional.html.twig', [
            'form' => $form,
            'active_section' => 'regional',
        ]);
    }

    #[Route('/currency-tax', name: 'app_settings_currency_tax', methods: ['GET', 'POST'])]
    public function currencyAndTax(Request $request, SettingsProvider $provider, SettingsManager $manager): Response
    {
        return $this->renderSimpleSection(
            $request,
            $provider,
            $manager,
            SettingsRegistry::SECTION_CURRENCY,
            'settings/currency_tax.html.twig',
            'app_settings_currency_tax',
            'flash.settings.currency_tax.saved',
        );
    }

    #[Route('/documents', name: 'app_settings_documents', methods: ['GET', 'POST'])]
    public function documents(Request $request, SettingsProvider $provider, SettingsManager $manager): Response
    {
        return $this->renderSimpleSection(
            $request,
            $provider,
            $manager,
            SettingsRegistry::SECTION_DOCUMENTS,
            'settings/documents.html.twig',
            'app_settings_documents',
            'flash.settings.documents.saved',
        );
    }

    #[Route('/email', name: 'app_settings_email', methods: ['GET', 'POST'])]
    public function email(Request $request, SettingsProvider $provider, SettingsManager $manager): Response
    {
        $section = SettingsRegistry::SECTION_EMAIL;
        // The secret password is managed by the dedicated newPassword input
        // below; never feed it through the array form data.
        $values = $provider->valuesForSection($section);
        unset($values[SettingsRegistry::KEY_SMTP_PASSWORD]);
        $form = $this->createSectionForm($section, $values);
        $form->add('newPassword', PasswordType::class, [
            'label' => 'settings.email.field.password',
            'help' => 'settings.email.help.password',
            'mapped' => false,
            'required' => false,
            'attr' => ['autocomplete' => 'new-password', 'placeholder' => '••••••••'],
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $manager->setMany($section, $form->getData());

            $newPassword = $form->get('newPassword')->getData();
            if (is_string($newPassword) && $newPassword !== '') {
                $manager->setSecret($section, SettingsRegistry::KEY_SMTP_PASSWORD, $newPassword);
            }

            $this->addFlash('success', 'flash.settings.email.saved');

            return $this->redirectToRoute('app_settings_email');
        }

        return $this->render('settings/email.html.twig', [
            'form' => $form,
            'has_password' => $provider->isSet($section, SettingsRegistry::KEY_SMTP_PASSWORD),
            'active_section' => 'email',
        ]);
    }

    /**
     * @param array<string, mixed> $values
     */
    private function createSectionForm(string $section, array $values): \Symfony\Component\Form\FormInterface
    {
        return $this->createForm(SettingsSectionType::class, $values, ['section' => $section]);
    }

    private function renderSimpleSection(
        Request $request,
        SettingsProvider $provider,
        SettingsManager $manager,
        string $section,
        string $template,
        string $route,
        string $flashKey,
    ): Response {
        $form = $this->createSectionForm($section, $provider->valuesForSection($section));
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $manager->setMany($section, $form->getData());
            $this->addFlash('success', $flashKey);

            return $this->redirectToRoute($route);
        }

        return $this->render($template, [
            'form' => $form,
            'active_section' => $this->activeSectionFromRoute($route),
        ]);
    }

    private function activeSectionFromRoute(string $route): string
    {
        return match ($route) {
            'app_settings_regional' => 'regional',
            'app_settings_currency_tax' => 'currency_tax',
            'app_settings_documents' => 'documents',
            'app_settings_email' => 'email',
            default => 'company',
        };
    }
}
