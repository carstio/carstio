<?php

declare(strict_types=1);

namespace App\Form\Attachment;

use App\Enum\AttachmentCategory;
use Symfony\Component\HttpFoundation\File\UploadedFile;

/**
 * Plain data carrier for the attachment upload form. Decouples the form
 * from the Attachment entity so the form can be reused for any owner.
 */
class AttachmentUploadData
{
    public ?UploadedFile $file = null;

    public AttachmentCategory $category = AttachmentCategory::Other;

    public ?string $description = null;
}
