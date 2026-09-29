<?php

declare(strict_types=1);

namespace App\Service\Workshop;

use App\Entity\Workshop\WorkOrder;
use App\Enum\AttachmentOwnerType;
use App\Repository\Attachment\AttachmentRepository;
use App\Service\Attachment\AttachmentStorage;
use App\Service\EntityDeleter;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Deletes a work order together with its attachments.
 *
 * Attachments reference their owner by (ownerType, ownerId) without a
 * foreign key, so the database cannot cascade the delete for us. The
 * attachment rows are removed in the same flush as the work order; the
 * physical files are removed only once that flush succeeded, so a blocked
 * delete leaves nothing half-removed.
 */
final class WorkOrderDeleter
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly EntityDeleter $entityDeleter,
        private readonly AttachmentRepository $attachmentRepository,
        private readonly AttachmentStorage $attachmentStorage,
    ) {
    }

    /**
     * Returns false when the database refused the delete (foreign key
     * violation), mirroring {@see EntityDeleter::delete()}.
     */
    public function delete(WorkOrder $workOrder): bool
    {
        $workOrderId = $workOrder->getId();
        $attachments = $workOrderId === null
            ? []
            : $this->attachmentRepository->findForOwner(AttachmentOwnerType::WorkOrder, $workOrderId);

        foreach ($attachments as $attachment) {
            $this->entityManager->remove($attachment);
        }

        if (!$this->entityDeleter->delete($workOrder)) {
            return false;
        }

        foreach ($attachments as $attachment) {
            $this->attachmentStorage->delete($attachment);
        }

        return true;
    }
}
