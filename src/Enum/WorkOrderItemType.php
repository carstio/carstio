<?php

namespace App\Enum;

enum WorkOrderItemType: string
{
    case Labor = 'labor';
    case Part = 'part';
    case Misc = 'misc';
}
