<?php

namespace App\Enum;

/**
 * Vehicle odometer units. Stored on WorkOrder as the short code (`km` /
 * `mi`) so historical readings keep their original unit even after the
 * Regional setting changes.
 *
 * The enum is the runtime view; persistence uses the bare string so old
 * rows survive future enum cleanup without exploding.
 */
enum MileageUnit: string
{
    case Kilometres = 'km';
    case Miles = 'mi';

    public const DEFAULT = self::Kilometres;

    /**
     * Translation key for the unit's long label, used in dropdowns and
     * helper text where the bare abbreviation would feel cryptic.
     */
    public function labelKey(): string
    {
        return match ($this) {
            self::Kilometres => 'mileage.unit.km',
            self::Miles => 'mileage.unit.mi',
        };
    }

    /**
     * @return array<string, string> map of label key => stored value, for ChoiceType.
     */
    public static function choices(): array
    {
        $choices = [];
        foreach (self::cases() as $case) {
            $choices[$case->labelKey()] = $case->value;
        }

        return $choices;
    }

    /**
     * @return list<string> list of valid codes for Assert\Choice.
     */
    public static function values(): array
    {
        return array_map(fn (self $c) => $c->value, self::cases());
    }
}
