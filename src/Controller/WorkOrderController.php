<?php

namespace App\Controller;

use App\Entity\User;
use App\Entity\Workshop\WorkOrder;
use App\Enum\AttachmentOwnerType;
use App\Enum\WorkOrderStatus;
use App\Form\Attachment\AttachmentUploadData;
use App\Form\Attachment\AttachmentUploadType;
use App\Form\WorkOrderType;
use App\Repository\Attachment\AttachmentRepository;
use App\Repository\Workshop\WorkOrderRepository;
use App\Service\Email\ClientDocumentMailer;
use App\Service\Email\Exception\DocumentEmailFailedException;
use App\Service\Email\Exception\IncompleteSmtpSettingsException;
use App\Service\Email\Exception\MissingClientEmailException;
use App\Service\EntityDeleter;
use App\Service\PartsMarginCalculator;
use App\Service\Pdf\CompanyProfileProvider;
use App\Service\Pdf\PdfRenderer;
use App\Service\WorkOrderCalculator;
use App\Service\WorkOrderFactory;
use App\Service\Workshop\WorkOrderMileageManager;
use App\Service\Workshop\WorkOrderPaymentSummarizer;
use App\Service\Workshop\WorkOrderStatusUpdater;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

#[Route('/work-orders')]
final class WorkOrderController extends AbstractController
{
    #[Route('', name: 'app_work_order_index', methods: ['GET'])]
    public function index(Request $request, WorkOrderRepository $repository): Response
    {
        $search = (string) $request->query->get('q', '');
        $statusFilter = (string) $request->query->get('status', '');

        return $this->render('work_order/index.html.twig', [
            'work_orders' => $repository->findForListing($search, $statusFilter),
            'search' => $search,
            'status_filter' => $statusFilter,
            'statuses' => WorkOrderStatus::cases(),
        ]);
    }

    #[Route('/new', name: 'app_work_order_new', methods: ['GET', 'POST'])]
    public function new(
        Request $request,
        EntityManagerInterface $em,
        WorkOrderFactory $factory,
        WorkOrderMileageManager $mileageManager,
        #[CurrentUser] User $currentUser,
    ): Response {
        $workOrder = $factory->createForUser($currentUser);

        $form = $this->createForm(WorkOrderType::class, $workOrder);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $mileageManager->applySnapshot($workOrder);
            $em->persist($workOrder);
            $em->flush();
            $this->addFlash('success', 'flash.work_order.created');

            return $this->redirectToRoute('app_work_order_show', ['id' => $workOrder->getId()]);
        }

        return $this->render('work_order/new.html.twig', [
            'form' => $form,
            'work_order' => $workOrder,
            'current_mileage_unit' => $mileageManager->resolveDefaultUnit(),
        ]);
    }

    #[Route('/{id}', name: 'app_work_order_show', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function show(
        WorkOrder $workOrder,
        WorkOrderCalculator $calculator,
        PartsMarginCalculator $partsMarginCalculator,
        AttachmentRepository $attachmentRepository,
    ): Response {
        $calculator->recalculate($workOrder);

        $attachmentForm = $this->createForm(AttachmentUploadType::class, new AttachmentUploadData(), [
            'action' => $this->generateUrl('app_work_order_attachment_upload', ['id' => $workOrder->getId()]),
        ]);

        return $this->render('work_order/show.html.twig', [
            'work_order' => $workOrder,
            'parts_margin' => $partsMarginCalculator->forWorkOrder($workOrder),
            'statuses' => WorkOrderStatus::cases(),
            'attachments' => $attachmentRepository->findForOwner(AttachmentOwnerType::WorkOrder, $workOrder->getId()),
            'attachment_form' => $attachmentForm,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_work_order_edit', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function edit(
        Request $request,
        WorkOrder $workOrder,
        EntityManagerInterface $em,
        WorkOrderCalculator $calculator,
        WorkOrderMileageManager $mileageManager,
        WorkOrderPaymentSummarizer $paymentSummarizer,
    ): Response {
        $form = $this->createForm(WorkOrderType::class, $workOrder, [
            'include_items' => true,
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $mileageManager->applySnapshot($workOrder);
            $calculator->recalculate($workOrder);
            $em->flush();
            $this->addFlash('success', 'flash.work_order.updated');

            return $this->redirectToRoute('app_work_order_show', ['id' => $workOrder->getId()]);
        }

        return $this->render('work_order/edit.html.twig', [
            'work_order' => $workOrder,
            'form' => $form,
            'payment_summary' => $paymentSummarizer->summarize($workOrder),
            'current_mileage_unit' => $workOrder->getOdometerUnit() ?? $mileageManager->resolveDefaultUnit(),
        ]);
    }

    #[Route('/{id}/delete', name: 'app_work_order_delete', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function delete(Request $request, WorkOrder $workOrder, EntityDeleter $deleter): Response
    {
        if ($this->isCsrfTokenValid('delete-wo-' . $workOrder->getId(), $request->request->get('_token'))) {
            if ($deleter->delete($workOrder)) {
                $this->addFlash('success', 'flash.work_order.deleted');
            } else {
                $this->addFlash('error', 'flash.delete.restricted');

                return $this->redirectToRoute('app_work_order_show', ['id' => $workOrder->getId()]);
            }
        }

        return $this->redirectToRoute('app_work_order_index');
    }

    #[Route('/{id}/pdf', name: 'app_work_order_pdf', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function pdf(
        WorkOrder $workOrder,
        WorkOrderCalculator $calculator,
        PdfRenderer $pdfRenderer,
        CompanyProfileProvider $companyProvider,
    ): Response {
        $calculator->recalculate($workOrder);

        return $pdfRenderer->downloadResponse(
            'pdf/work_order.html.twig',
            [
                'work_order' => $workOrder,
                'company' => $companyProvider->getCompany(),
                'logo_path' => $companyProvider->getLogoAbsolutePath(),
                'document_footer_text' => $companyProvider->getDocumentFooterText(),
            ],
            sprintf('work-order-%s.pdf', $workOrder->getNumber() ?? (string) $workOrder->getId()),
        );
    }

    #[Route('/{id}/email', name: 'app_work_order_send_email', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function sendEmail(
        Request $request,
        WorkOrder $workOrder,
        ClientDocumentMailer $mailer,
        WorkOrderCalculator $calculator,
    ): Response {
        if (!$this->isCsrfTokenValid('wo-email-' . $workOrder->getId(), $request->request->get('_token'))) {
            return $this->redirectToRoute('app_work_order_show', ['id' => $workOrder->getId()]);
        }

        $calculator->recalculate($workOrder);

        try {
            $mailer->sendWorkOrder($workOrder);
            $this->addFlash('success', 'flash.work_order.email_sent');
        } catch (MissingClientEmailException) {
            $this->addFlash('error', 'flash.email.client_missing_address');
        } catch (IncompleteSmtpSettingsException) {
            $this->addFlash('error', 'flash.email.smtp_incomplete');
        } catch (DocumentEmailFailedException) {
            $this->addFlash('error', 'flash.email.send_failed');
        }

        return $this->redirectToRoute('app_work_order_show', ['id' => $workOrder->getId()]);
    }

    #[Route('/{id}/status', name: 'app_work_order_status', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function updateStatus(Request $request, WorkOrder $workOrder, WorkOrderStatusUpdater $updater): Response
    {
        if (!$this->isCsrfTokenValid('wo-status-' . $workOrder->getId(), $request->request->get('_token'))) {
            return $this->redirectToRoute('app_work_order_show', ['id' => $workOrder->getId()]);
        }

        $status = WorkOrderStatus::tryFrom((string) $request->request->get('status'));
        if ($status === null) {
            return $this->redirectToRoute('app_work_order_show', ['id' => $workOrder->getId()]);
        }

        $updater->update($workOrder, $status);
        $this->addFlash('success', 'flash.work_order.status_updated');

        return $this->redirectToRoute('app_work_order_show', ['id' => $workOrder->getId()]);
    }
}
