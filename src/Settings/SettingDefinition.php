<?php

namespace App\Settings;

use App\Enum\SettingType;
use Symfony\Component\Validator\Constraint;

/**
 * Immutable description of a single setting. Combined together they form
 * the {@see SettingsRegistry} - the authoritative list of what the
 * application knows how to read or write.
 */
final readonly class SettingDefinition
{
    /**
     * @param array<string, string|array<string, string>>|null $choices Map of label key => stored value (or of group label => such a map), used when type is Choice.
     * @param list<Constraint>           $constraints Extra validators applied by the dynamic settings form.
     */
    public function __construct(
        public string $section,
        public string $key,
        public SettingType $type,
        public mixed $default = null,
        public string $labelKey = '',
        public ?string $helpKey = null,
        public ?string $placeholder = null,
        public bool $required = false,
        public ?array $choices = null,
        public int $sortOrder = 0,
        public array $constraints = [],
        public bool $autocomplete = false,
    ) {
    }

    public function id(): string
    {
        return $this->section . '.' . $this->key;
    }

    public function isSecret(): bool
    {
        return $this->type->isSecret();
    }
}
