<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Adds the baseline browser hardening headers to every main response so
 * they do not depend on the web server in front of PHP-FPM.
 *
 * Headers already set by a controller (or a reverse proxy) are left alone.
 */
final class SecurityHeadersSubscriber implements EventSubscriberInterface
{
    private const HEADERS = [
        // Never let browsers sniff a different content type for uploads / PDFs.
        'X-Content-Type-Options' => 'nosniff',
        // The back office is never meant to be embedded in a frame.
        'X-Frame-Options' => 'DENY',
        // Same-origin referers keep the CSRF origin checks and the locale
        // switcher working without leaking URLs to third parties.
        'Referrer-Policy' => 'same-origin',
    ];

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::RESPONSE => 'onKernelResponse',
        ];
    }

    public function onKernelResponse(ResponseEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $headers = $event->getResponse()->headers;
        foreach (self::HEADERS as $name => $value) {
            if (!$headers->has($name)) {
                $headers->set($name, $value);
            }
        }
    }
}
