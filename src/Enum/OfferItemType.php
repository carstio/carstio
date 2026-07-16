<?php

namespace App\Enum;

enum OfferItemType: string
{
    case Labor = 'labor';
    case Part = 'part';
    case Misc = 'misc';
}
