<?php

namespace App\Service\Settings;

use App\Entity\Settings\Setting;
use App\Enum\SettingType;
use App\Repository\Settings\SettingRepository;
use App\Settings\SettingDefinition;
use App\Settings\SettingsRegistry;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Write-side facade for application settings.
 *
 * The manager only persists keys declared in {@see SettingsRegistry}; any
 * other tuple is rejected. Values are coerced to a string for storage and
 * the type column is taken from the registry so future tooling can read
 * rows without consulting the registry.
 *
 * Secret-typed settings are encrypted via {@see SmtpPasswordEncrypter}
 * before storage. Callers should pass plaintext (never ciphertext) here.
 */
class SettingsManager
{
    public function __construct(
        private readonly SettingRepository $repository,
        private readonly EntityManagerInterface $entityManager,
        private readonly SettingsRegistry $registry,
        private readonly SettingsProvider $provider,
        private readonly SmtpPasswordEncrypter $passwordEncrypter,
    ) {
    }

    /**
     * Sets a single value. Pass null to clear the stored value back to the
     * registry default. The change is flushed immediately.
     */
    public function set(string $section, string $key, mixed $value): void
    {
        $definition = $this->registry->get($section, $key);
        $this->writeOne($definition, $value);
        $this->entityManager->flush();
        $this->provider->clearCache();
    }

    /**
     * Bulk-saves a section. The submitted array maps registry keys to
     * values. Missing keys are left untouched (this is what makes the
     * "leave SMTP password empty to keep" behaviour work in the form).
     *
     * @param array<string, mixed> $values
     */
    public function setMany(string $section, array $values): void
    {
        foreach ($values as $key => $value) {
            $definition = $this->registry->get($section, $key);
            // Secret values are write-only via setSecret(); skip them here
            // so callers cannot accidentally clear ciphertext by passing a
            // fresh form array that happens to contain the secret key.
            if ($definition->isSecret()) {
                continue;
            }
            $this->writeOne($definition, $value);
        }

        $this->entityManager->flush();
        $this->provider->clearCache();
    }

    /**
     * Stores a path on a FilePath setting (e.g. logo upload). Used by the
     * controller after {@see LogoUploader} produces a public path.
     */
    public function setFilePath(string $section, string $key, ?string $publicPath): void
    {
        $definition = $this->registry->get($section, $key);
        if ($definition->type !== SettingType::FilePath) {
            throw new \LogicException(sprintf('Setting "%s" is not a file path.', $definition->id()));
        }

        $this->writeOne($definition, $publicPath);
        $this->entityManager->flush();
        $this->provider->clearCache();
    }

    /**
     * Encrypts and stores a plaintext secret. Used by the controller for
     * the SMTP password input - the entity never sees the plaintext.
     */
    public function setSecret(string $section, string $key, string $plaintext): void
    {
        $definition = $this->registry->get($section, $key);
        if (!$definition->isSecret()) {
            throw new \LogicException(sprintf('Setting "%s" is not declared as a secret.', $definition->id()));
        }

        $cipher = $this->passwordEncrypter->encrypt($plaintext);
        $this->writeRaw($definition, $cipher);
        $this->entityManager->flush();
        $this->provider->clearCache();
    }

    private function writeOne(SettingDefinition $definition, mixed $value): void
    {
        if ($definition->isSecret()) {
            // Secrets must travel through setSecret() to ensure encryption.
            // Allow null to clear, though.
            if ($value === null || $value === '') {
                $this->writeRaw($definition, null);

                return;
            }

            throw new \LogicException(sprintf(
                'Use setSecret() to write secret setting "%s".',
                $definition->id(),
            ));
        }

        $this->writeRaw($definition, $this->normalize($definition, $value));
    }

    private function writeRaw(SettingDefinition $definition, ?string $value): void
    {
        $entity = $this->repository->findOneByKey($definition->section, $definition->key);
        if ($entity === null) {
            $entity = new Setting($definition->section, $definition->key, $definition->type->value);
            $entity->setSortOrder($definition->sortOrder);
            $this->entityManager->persist($entity);
        }

        $entity->setValue($value);
    }

    private function normalize(SettingDefinition $definition, mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return match ($definition->type) {
            SettingType::Boolean => $value ? '1' : '0',
            SettingType::Integer => (string) (int) $value,
            SettingType::Float => (string) (float) $value,
            default => is_string($value) ? $value : (string) $value,
        };
    }
}
