<?php

namespace App\Twig;

use App\Enum\SupportedLocale;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * Exposes the supported-locale registry to templates so the language
 * switcher does not need to hard-code its options.
 */
class LocaleExtension extends AbstractExtension
{
    public function getFunctions(): array
    {
        return [
            new TwigFunction('supported_locales', [$this, 'supportedLocales']),
        ];
    }

    /**
     * @return array<string, string> map of native name => locale code
     */
    public function supportedLocales(): array
    {
        return SupportedLocale::choices();
    }
}
