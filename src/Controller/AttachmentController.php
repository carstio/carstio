<?php

namespace App\Controller;

use App\Entity\Attachment\Attachment;
use App\Entity\User;
use App\Entity\Workshop\WorkOrder;
use App\Form\Attachment\AttachmentUploadData;
use App\Form\Attachment\AttachmentUploadType;
use App\Service\Attachment\AttachmentManager;
use App\Service\Attachment\AttachmentStorage;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

final class AttachmentController extends AbstractController
{
    #[Route(
        '/work-orders/{id}/attachments',
        name: 'app_work_order_attachment_upload',
        requirements: ['id' => '\d+'],
        methods: ['POST'],
    )]
    public function uploadForWorkOrder(
        Request $request,
        WorkOrder $workOrder,
        AttachmentManager $manager,
        #[CurrentUser] ?User $currentUser,
    ): Response {
        $data = new AttachmentUploadData();
        $form = $this->createForm(AttachmentUploadType::class, $data);
        $form->handleRequest($request);

        if (!$form->isSubmitted() || !$form->isValid()) {
            $this->addFlash('error', 'flash.attachment.upload_failed');

            return $this->redirectToRoute('app_work_order_show', ['id' => $workOrder->getId()]);
        }

        if ($data->file === null) {
            $this->addFlash('error', 'flash.attachment.upload_failed');

            return $this->redirectToRoute('app_work_order_show', ['id' => $workOrder->getId()]);
        }

        $manager->createForWorkOrder(
            $workOrder,
            $data->file,
            $data->category,
            $data->description,
            $currentUser,
        );

        $this->addFlash('success', 'flash.attachment.uploaded');

        return $this->redirectToRoute('app_work_order_show', ['id' => $workOrder->getId()]);
    }

    #[Route(
        '/attachments/{id}/download',
        name: 'app_attachment_download',
        requirements: ['id' => '\d+'],
        methods: ['GET'],
    )]
    public function download(Attachment $attachment, AttachmentStorage $storage): Response
    {
        return $this->fileResponse($attachment, $storage, HeaderUtils::DISPOSITION_ATTACHMENT);
    }

    #[Route(
        '/attachments/{id}/view',
        name: 'app_attachment_view',
        requirements: ['id' => '\d+'],
        methods: ['GET'],
    )]
    public function view(Attachment $attachment, AttachmentStorage $storage): Response
    {
        if (!$attachment->isInlineSafe()) {
            return $this->redirectToRoute('app_attachment_download', ['id' => $attachment->getId()]);
        }

        return $this->fileResponse($attachment, $storage, HeaderUtils::DISPOSITION_INLINE);
    }

    #[Route(
        '/attachments/{id}/delete',
        name: 'app_attachment_delete',
        requirements: ['id' => '\d+'],
        methods: ['POST'],
    )]
    public function delete(
        Request $request,
        Attachment $attachment,
        AttachmentManager $manager,
    ): Response {
        if (!$this->isCsrfTokenValid('delete-attachment-' . $attachment->getId(), $request->request->get('_token'))) {
            return $this->redirectToOwner($attachment);
        }

        $redirect = $this->redirectToOwner($attachment);

        $manager->delete($attachment);
        $this->addFlash('success', 'flash.attachment.deleted');

        return $redirect;
    }

    private function fileResponse(Attachment $attachment, AttachmentStorage $storage, string $disposition): Response
    {
        if (!$storage->exists($attachment)) {
            $this->addFlash('error', 'flash.attachment.not_found');

            return $this->redirectToOwner($attachment);
        }

        $response = new BinaryFileResponse($storage->absolutePath($attachment));
        $response->setContentDisposition(
            $disposition,
            $attachment->getOriginalFilename(),
            // ASCII fallback for old user agents (the original filename
            // may contain non-ASCII characters that BinaryFileResponse
            // does not transliterate by default).
            preg_replace('/[^\x20-\x7E]/', '_', $attachment->getOriginalFilename()) ?? 'attachment',
        );
        $response->headers->set('Content-Type', $attachment->getMimeType());

        return $response;
    }

    private function redirectToOwner(Attachment $attachment): Response
    {
        return match ($attachment->getOwnerType()) {
            \App\Enum\AttachmentOwnerType::WorkOrder => $this->redirectToRoute('app_work_order_show', ['id' => $attachment->getOwnerId()]),
            default => $this->redirectToRoute('app_dashboard'),
        };
    }
}
