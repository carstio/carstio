<?php

namespace App\Entity\Settings;

/**
 * Plain constants for regional defaults. Kept in the Settings namespace so
 * the registry, fixtures, and any future consumers share one source.
 */
final class RegionalDefaults
{
    public const LOCALE = 'en';
    public const TIMEZONE = 'UTC';
    public const DATE_FORMAT = 'Y-m-d';
    public const TIME_FORMAT = 'H:i';
    public const MILEAGE_UNIT = 'km';

    private function __construct()
    {
    }
}
