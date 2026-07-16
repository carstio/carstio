<?php

declare(strict_types=1);

namespace App\Service\Attachment;

use App\Entity\Attachment\Attachment;
use App\Entity\User;
use App\Entity\Workshop\WorkOrder;
use App\Enum\AttachmentCategory;
use App\Enum\AttachmentOwnerType;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;

/**
 * Coordinates attachment persistence and physical storage. Controllers
 * and forms only ever talk to this service so the entity, the disk, and
 * the database stay in sync.
 *
 * Only WorkOrder owners are accepted today. Other owner types listed in
 * {@see AttachmentOwnerType} are rejected here until they are wired up
 * intentionally.
 */
class AttachmentManager
{
    /** @var list<AttachmentOwnerType> */
    private const SUPPORTED_OWNER_TYPES = [
        AttachmentOwnerType::WorkOrder,
    ];

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly AttachmentStorage $storage,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function isOwnerSupported(AttachmentOwnerType $ownerType): bool
    {
        return in_array($ownerType, self::SUPPORTED_OWNER_TYPES, true);
    }

    public function createForWorkOrder(
        WorkOrder $workOrder,
        UploadedFile $file,
        AttachmentCategory $category,
        ?string $description,
        ?User $uploadedBy,
    ): Attachment {
        $workOrderId = $workOrder->getId();
        if ($workOrderId === null) {
            throw new \InvalidArgumentException('Cannot attach a file to an unsaved work order.');
        }

        return $this->create(
            AttachmentOwnerType::WorkOrder,
            $workOrderId,
            $file,
            $category,
            $description,
            $uploadedBy,
        );
    }

    public function create(
        AttachmentOwnerType $ownerType,
        int $ownerId,
        UploadedFile $file,
        AttachmentCategory $category,
        ?string $description,
        ?User $uploadedBy,
    ): Attachment {
        if (!$this->isOwnerSupported($ownerType)) {
            throw new \InvalidArgumentException(sprintf(
                'Owner type "%s" is not enabled for attachments yet.',
                $ownerType->value,
            ));
        }

        $stored = $this->storage->store($file, $ownerType);

        $attachment = new Attachment();
        $attachment->setOwnerType($ownerType);
        $attachment->setOwnerId($ownerId);
        $attachment->setCategory($category);
        $attachment->setOriginalFilename($stored['originalFilename']);
        $attachment->setStoredFilename($stored['storedFilename']);
        $attachment->setStoragePath($stored['storagePath']);
        $attachment->setMimeType($stored['mimeType']);
        $attachment->setSize($stored['size']);
        $attachment->setExtension($stored['extension']);
        $attachment->setDescription($description !== null && $description !== '' ? $description : null);
        $attachment->setUploadedBy($uploadedBy);

        $this->em->persist($attachment);
        $this->em->flush();

        return $attachment;
    }

    /**
     * Removes the attachment record AND the physical file. A missing
     * physical file is logged but does not block removal of the
     * database row, so orphaned records can always be cleaned up.
     */
    public function delete(Attachment $attachment): void
    {
        if (!$this->storage->exists($attachment)) {
            $this->logger->warning('Attachment file missing on disk during delete.', [
                'attachmentId' => $attachment->getId(),
                'storagePath' => $attachment->getStoragePath(),
            ]);
        } else {
            $this->storage->delete($attachment);
        }

        $this->em->remove($attachment);
        $this->em->flush();
    }
}
