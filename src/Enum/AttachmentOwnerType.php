<?php

namespace App\Enum;

/**
 * Discriminator for the polymorphic Attachment.ownerType column.
 *
 * Only WorkOrder is wired up in the UI today; the other cases are kept
 * here so the data model can round-trip them when those owners are
 * enabled (see {@see \App\Service\Attachment\AttachmentManager}).
 */
enum AttachmentOwnerType: string
{
    case WorkOrder = 'work_order';
    case Offer = 'offer';
    case Car = 'car';
    case Client = 'client';
    case Payment = 'payment';
    case Inspection = 'inspection';
    case Invoice = 'invoice';
}
