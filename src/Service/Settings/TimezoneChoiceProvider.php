<?php

declare(strict_types=1);

namespace App\Service\Settings;

/**
 * Builds the choice list for the regional timezone setting.
 *
 * Sources identifiers from {@see \DateTimeZone::listIdentifiers()} so the
 * application stays in sync with whatever the running PHP/IANA database
 * exposes. Identifiers are grouped by their continent prefix (Europe,
 * America, ...) and identifiers without a slash (UTC, GMT, ...) are placed
 * in a dedicated "UTC & generic" group at the top.
 */
final class TimezoneChoiceProvider
{
    /**
     * @return array<string, array<string, string>> map of group label =>
     * (identifier => identifier) suitable for Symfony Form ChoiceType.
     */
    public function groupedChoices(): array
    {
        $identifiers = \DateTimeZone::listIdentifiers();
        sort($identifiers);

        $generic = [];
        $byGroup = [];

        foreach ($identifiers as $identifier) {
            $slashAt = strpos($identifier, '/');
            if ($slashAt === false) {
                $generic[$identifier] = $identifier;
                continue;
            }

            $group = substr($identifier, 0, $slashAt);
            $byGroup[$group][$identifier] = $identifier;
        }

        ksort($byGroup);

        $choices = [];
        if ($generic !== []) {
            $choices['settings.regional.timezone_group.generic'] = $generic;
        }
        foreach ($byGroup as $group => $entries) {
            $choices[$group] = $entries;
        }

        return $choices;
    }

    public function isValid(?string $identifier): bool
    {
        if ($identifier === null || $identifier === '') {
            return false;
        }

        return in_array($identifier, \DateTimeZone::listIdentifiers(), true);
    }
}
