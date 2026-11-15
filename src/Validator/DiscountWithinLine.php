<?php

declare(strict_types=1);

namespace App\Validator;

use Symfony\Component\Validator\Constraint;

/**
 * For one line of a work order or an offer: the discount is an amount, and
 * it cannot be more than the line is worth (quantity x unit price) - a
 * larger one would turn the line, and possibly the document, negative.
 */
final class DiscountWithinLine extends Constraint
{
    public string $message = 'item.error.discount_exceeds_line';

    public function getTargets(): string
    {
        return self::CLASS_CONSTRAINT;
    }
}
