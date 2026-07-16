<?php

namespace App\Controller;

use App\Entity\User;
use App\Enum\SupportedLocale;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Switches the active locale.
 *
 * Writing to the session takes effect on the next request (the
 * {@see \App\EventSubscriber\LocaleSubscriber} reads it). When the user is
 * authenticated, the preference is also persisted to their profile so it
 * follows them across devices and sessions.
 */
final class LocaleController extends AbstractController
{
    #[Route('/locale/{locale}', name: 'app_locale_switch', requirements: ['locale' => '[a-zA-Z]{2,5}'], methods: ['POST'])]
    public function switch(string $locale, Request $request, EntityManagerInterface $em): RedirectResponse
    {
        if (!$this->isCsrfTokenValid('locale-switch', (string) $request->request->get('_token'))) {
            return $this->redirect($this->safeRedirectTarget($request));
        }

        if (SupportedLocale::isSupported($locale)) {
            if ($request->hasSession()) {
                $request->getSession()->set('_locale', $locale);
            }

            $user = $this->getUser();
            if ($user instanceof User) {
                $user->setPreferredLocale($locale);
                $em->flush();
            }
        }

        return $this->redirect($this->safeRedirectTarget($request));
    }

    /**
     * Only follow the Referer when it points back to our own host. Falls
     * back to the dashboard for authenticated users and to the login page
     * for everyone else, which keeps the switcher free of open-redirect risk.
     */
    private function safeRedirectTarget(Request $request): string
    {
        $referer = $request->headers->get('referer');
        if (is_string($referer) && $referer !== '') {
            $host = parse_url($referer, PHP_URL_HOST);
            if ($host !== false && $host === $request->getHost()) {
                return $referer;
            }
        }

        return $this->generateUrl($this->getUser() ? 'app_dashboard' : 'app_login');
    }
}
