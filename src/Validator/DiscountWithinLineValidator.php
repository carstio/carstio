<?php

declare(strict_types=1);

namespace App\Validator;

use App\Entity\Sales\OfferItem;
use App\Entity\Workshop\WorkOrderItem;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;
use Symfony\Component\Validator\Exception\UnexpectedValueException;

final class DiscountWithinLineValidator extends ConstraintValidator
{
    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$constraint instanceof DiscountWithinLine) {
            throw new UnexpectedTypeException($constraint, DiscountWithinLine::class);
        }

        if ($value === null) {
            return;
        }

        if (!$value instanceof WorkOrderItem && !$value instanceof OfferItem) {
            throw new UnexpectedValueException($value, WorkOrderItem::class . '|' . OfferItem::class);
        }

        // In whole cents, so that a discount of exactly the line amount (a
        // free item) is never refused because of binary rounding.
        $lineCents = (int) round((float) $value->getQuantity() * (float) $value->getUnitPrice() * 100);
        $discountCents = (int) round((float) $value->getDiscountAmount() * 100);

        if ($discountCents > $lineCents) {
            $this->context->buildViolation($constraint->message)
                ->atPath('discountAmount')
                ->addViolation();
        }
    }
}
