<?php

namespace App\Entity\Settings;

use App\Repository\Settings\SettingRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * Generic key/value record for application settings.
 *
 * The schema is intentionally flat: one row per setting, identified by
 * (section, key). Types live as strings (see {@see \App\Enum\SettingType})
 * so reading code can cast the value back to its expected PHP type.
 *
 * The set of valid (section, key) tuples is governed by
 * {@see \App\Settings\SettingsRegistry}; the database is just storage.
 * Adding a new setting is a PHP-only change - no migration required.
 *
 * Application code should not access this entity directly. Use
 * {@see \App\Service\Settings\SettingsProvider} to read and
 * {@see \App\Service\Settings\SettingsManager} to write.
 */
#[ORM\Entity(repositoryClass: SettingRepository::class)]
#[ORM\Table(name: 'setting')]
#[ORM\UniqueConstraint(name: 'uniq_setting_section_key', columns: ['section', 'setting_key'])]
#[ORM\Index(name: 'idx_setting_section', columns: ['section'])]
class Setting
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 64)]
    private string $section;

    /**
     * Stored under the column name "setting_key" to avoid colliding with
     * SQL reserved words across engines, while keeping the property name
     * intuitive for code that reads/writes settings.
     */
    #[ORM\Column(name: 'setting_key', length: 128)]
    private string $key;

    /**
     * Raw stored value. Always serialized to a string by the manager;
     * casting back to the expected PHP type happens in the provider so
     * empty/missing values can be distinguished from explicit zeros.
     */
    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $value = null;

    /**
     * String identifier for the value type (matches SettingType cases).
     * Stored alongside the value so future tooling can interpret rows
     * without consulting the registry.
     */
    #[ORM\Column(length: 32)]
    private string $type;

    #[ORM\Column(name: 'sort_order', type: 'smallint', options: ['default' => 0])]
    private int $sortOrder = 0;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column]
    private \DateTimeImmutable $updatedAt;

    public function __construct(string $section, string $key, string $type)
    {
        $this->section = $section;
        $this->key = $key;
        $this->type = $type;
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getSection(): string
    {
        return $this->section;
    }

    public function getKey(): string
    {
        return $this->key;
    }

    public function getValue(): ?string
    {
        return $this->value;
    }

    public function setValue(?string $value): static
    {
        $this->value = $value;
        $this->updatedAt = new \DateTimeImmutable();

        return $this;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function setType(string $type): static
    {
        $this->type = $type;

        return $this;
    }

    public function getSortOrder(): int
    {
        return $this->sortOrder;
    }

    public function setSortOrder(int $sortOrder): static
    {
        $this->sortOrder = $sortOrder;

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }
}
