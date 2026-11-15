<?php

declare(strict_types=1);

namespace App\Validator;

use Symfony\Component\Validator\Constraint;

/**
 * For a whole work order or offer: what its lines add up to must fit the
 * columns the totals are stored in. Each line can be within its limits and
 * the sum still be too large.
 */
final class StorableTotals extends Constraint
{
    public string $message = 'document.error.total_too_large';

    public function getTargets(): string
    {
        return self::CLASS_CONSTRAINT;
    }
}
