<?php

namespace App\Service\Settings;

use App\Entity\Settings\Setting;
use App\Enum\SettingType;
use App\Repository\Settings\SettingRepository;
use App\Settings\SettingDefinition;
use App\Settings\SettingsRegistry;

/**
 * Read-side facade for application settings.
 *
 * Loads every setting once per request, caches them in memory, and casts
 * raw stored strings to the type declared by the registry. When a setting
 * has no DB row, the registry default is returned. The provider does not
 * decrypt secret values - that's a deliberate boundary so a leaked
 * provider doesn't expose credentials. Use {@see decryptSecret()} when a
 * caller actually needs the plaintext (e.g. the future mailer).
 */
class SettingsProvider
{
    /**
     * @var array<string, ?string>|null Raw values keyed by "section.key", null until warmed
     */
    private ?array $rawCache = null;

    public function __construct(
        private readonly SettingRepository $repository,
        private readonly SettingsRegistry $registry,
        private readonly SmtpPasswordEncrypter $passwordEncrypter,
    ) {
    }

    public function get(string $section, string $key): mixed
    {
        $definition = $this->registry->get($section, $key);
        $raw = $this->rawValue($section, $key);

        if ($raw === null) {
            return $definition->default;
        }

        return $this->cast($raw, $definition);
    }

    public function getString(string $section, string $key): ?string
    {
        $value = $this->get($section, $key);

        return $value === null ? null : (string) $value;
    }

    public function getInt(string $section, string $key): ?int
    {
        $value = $this->get($section, $key);
        if ($value === null || $value === '') {
            return null;
        }

        return (int) $value;
    }

    public function getFloat(string $section, string $key): ?float
    {
        $value = $this->get($section, $key);
        if ($value === null || $value === '') {
            return null;
        }

        return (float) $value;
    }

    public function getBool(string $section, string $key): bool
    {
        $value = $this->get($section, $key);

        return $value === true || $value === '1' || $value === 'true';
    }

    /**
     * Decrypts a secret setting (e.g. SMTP password). Returns null when no
     * secret is stored or decryption fails (e.g. APP_SECRET rotated).
     */
    public function decryptSecret(string $section, string $key): ?string
    {
        $definition = $this->registry->get($section, $key);
        if (!$definition->isSecret()) {
            throw new \LogicException(sprintf(
                'Setting "%s" is not declared as a secret.',
                $definition->id(),
            ));
        }

        return $this->passwordEncrypter->decrypt($this->rawValue($section, $key));
    }

    /**
     * True when a stored value exists for a key (regardless of content).
     * Useful for "is a password set?" UI hints.
     */
    public function isSet(string $section, string $key): bool
    {
        $raw = $this->rawValue($section, $key);

        return $raw !== null && $raw !== '';
    }

    /**
     * Returns one record per registry definition for a section, with the
     * resolved (cast) value applied. Used by the dynamic settings form to
     * pre-fill its fields.
     *
     * @return array<string, mixed> map of key => value
     */
    public function valuesForSection(string $section): array
    {
        $values = [];
        foreach ($this->registry->bySection($section) as $definition) {
            $values[$definition->key] = $this->get($section, $definition->key);
        }

        return $values;
    }

    /**
     * Drops the in-memory cache. Called by SettingsManager after a write
     * so subsequent reads see fresh data.
     */
    public function clearCache(): void
    {
        $this->rawCache = null;
    }

    private function rawValue(string $section, string $key): ?string
    {
        $this->warmCache();

        return $this->rawCache[$section . '.' . $key] ?? null;
    }

    private function warmCache(): void
    {
        if ($this->rawCache !== null) {
            return;
        }

        $cache = [];
        foreach ($this->repository->findAllSettings() as $setting) {
            $cache[$setting->getSection() . '.' . $setting->getKey()] = $setting->getValue();
        }

        $this->rawCache = $cache;
    }

    private function cast(string $raw, SettingDefinition $definition): mixed
    {
        return match ($definition->type) {
            SettingType::Integer => $raw === '' ? null : (int) $raw,
            SettingType::Float => $raw === '' ? null : (float) $raw,
            SettingType::Boolean => $raw === '1' || $raw === 'true',
            SettingType::Secret => $raw, // Stay as ciphertext on read; callers decrypt explicitly.
            default => $raw,
        };
    }
}
