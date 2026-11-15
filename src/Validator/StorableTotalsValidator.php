<?php

declare(strict_types=1);

namespace App\Validator;

use App\Entity\Sales\Offer;
use App\Entity\Workshop\WorkOrder;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;
use Symfony\Component\Validator\Exception\UnexpectedValueException;

final class StorableTotalsValidator extends ConstraintValidator
{
    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$constraint instanceof StorableTotals) {
            throw new UnexpectedTypeException($constraint, StorableTotals::class);
        }

        if ($value === null) {
            return;
        }

        if (!$value instanceof WorkOrder && !$value instanceof Offer) {
            throw new UnexpectedValueException($value, WorkOrder::class . '|' . Offer::class);
        }

        // Before discounts (the subtotals) and after discounts and tax (the
        // total). With discounts and tax rates inside their own limits,
        // every other stored figure is smaller than one of these two.
        $gross = 0.0;
        $total = 0.0;
        foreach ($value->getItems() as $item) {
            $line = (float) $item->getQuantity() * (float) $item->getUnitPrice();
            $gross += $line;
            $total += ($line - (float) $item->getDiscountAmount()) * (1 + (float) $item->getTaxRate() / 100);
        }

        if (max($gross, $total) > StorableAmount::MAX) {
            $this->context->buildViolation($constraint->message)->addViolation();
        }
    }
}
