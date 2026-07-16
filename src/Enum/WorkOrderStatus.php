<?php

namespace App\Enum;

enum WorkOrderStatus: string
{
    case Draft = 'draft';
    case Open = 'open';
    case InProgress = 'in_progress';
    case WaitingParts = 'waiting_parts';
    case WaitingApproval = 'waiting_approval';
    case Completed = 'completed';
    case Cancelled = 'cancelled';
    case Closed = 'closed';
}
