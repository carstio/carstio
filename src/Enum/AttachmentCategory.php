<?php

namespace App\Enum;

enum AttachmentCategory: string
{
    case Photo = 'photo';
    case Document = 'document';
    case Diagnostic = 'diagnostic';
    case BeforeRepair = 'before_repair';
    case AfterRepair = 'after_repair';
    case Receipt = 'receipt';
    case Other = 'other';
}
