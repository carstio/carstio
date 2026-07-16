<?php

namespace App\EventSubscriber;

use App\Entity\User;
use App\Enum\SupportedLocale;
use App\Service\Settings\SettingsProvider;
use App\Settings\SettingsRegistry;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Contracts\Translation\LocaleAwareInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Resolves the request locale on every main request.
 *
 * Order of preference:
 *   1. Authenticated user's preferredLocale.
 *   2. _locale stored in the session (set by the locale switcher).
 *   3. The Settings.default_locale value.
 *   4. The framework default locale (kept implicit; we don't override it).
 *
 * Runs at priority 7 - after Symfony's firewall (priority 8) so the
 * authenticated user is available, but before the controller is resolved so
 * Twig and form translators see the right locale on first render.
 */
class LocaleSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly Security $security,
        private readonly SettingsProvider $settingsProvider,
        private readonly TranslatorInterface $translator,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => [['onKernelRequest', 7]],
        ];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        $locale = $this->resolveLocale($request);
        if ($locale !== null) {
            $request->setLocale($locale);
            // The Symfony LocaleAwareListener (priority 15) has already
            // synced the translator with the previous (default) locale at
            // this point. Update the translator directly so Twig and form
            // labels render in the resolved locale.
            if ($this->translator instanceof LocaleAwareInterface) {
                $this->translator->setLocale($locale);
            }
        }
    }

    private function resolveLocale(\Symfony\Component\HttpFoundation\Request $request): ?string
    {
        $user = $this->security->getUser();
        if ($user instanceof User && SupportedLocale::isSupported($user->getPreferredLocale())) {
            return $user->getPreferredLocale();
        }

        if ($request->hasSession()) {
            $session = $request->getSession();
            $sessionLocale = $session->get('_locale');
            if (is_string($sessionLocale) && SupportedLocale::isSupported($sessionLocale)) {
                return $sessionLocale;
            }
        }

        try {
            $default = $this->settingsProvider->getString(
                SettingsRegistry::SECTION_REGIONAL,
                SettingsRegistry::KEY_DEFAULT_LOCALE,
            );
            if (SupportedLocale::isSupported($default)) {
                return $default;
            }
        } catch (\Throwable) {
            // Settings table may not exist yet (e.g. before first migration);
            // we silently fall back to the framework default.
        }

        return null;
    }
}
