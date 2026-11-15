<?php

declare(strict_types=1);

namespace App\Validator;

use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Constraints\Compound;

/**
 * An amount of money (or a quantity) that is not negative and fits the
 * DECIMAL(10,2) columns amounts are stored in.
 *
 * Browsers enforce "min" on a number field but nothing stops a larger or a
 * hand-made value; without this the database refuses it and the page
 * fails instead of showing an error next to the field.
 */
#[\Attribute(\Attribute::TARGET_PROPERTY)]
final class StorableAmount extends Compound
{
    public const MAX = 99999999.99;

    protected function getConstraints(array $options): array
    {
        return [
            new Assert\PositiveOrZero(),
            new Assert\LessThanOrEqual(self::MAX),
        ];
    }
}
