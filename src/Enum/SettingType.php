<?php

namespace App\Enum;

/**
 * The type of a registered setting. Persisted as a string so old rows stay
 * readable if a case is added or renamed; consumers go through
 * {@see \App\Service\Settings\SettingsProvider} which handles casting.
 */
enum SettingType: string
{
    case String = 'string';
    case Text = 'text';
    case Email = 'email';
    case Url = 'url';
    case Integer = 'integer';
    case Float = 'float';
    case Boolean = 'boolean';
    case Choice = 'choice';
    /** Stored as ciphertext, never echoed back to the UI. */
    case Secret = 'secret';
    /** Path to an uploaded file (logo, etc.). Stored as a string. */
    case FilePath = 'file_path';

    public function isSecret(): bool
    {
        return $this === self::Secret;
    }
}
