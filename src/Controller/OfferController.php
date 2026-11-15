<?php

namespace App\Controller;

use App\Entity\Sales\Offer;
use App\Entity\User;
use App\Enum\OfferStatus;
use App\Form\OfferType;
use App\Repository\Sales\OfferRepository;
use App\Service\Email\ClientDocumentMailer;
use App\Service\Email\Exception\DocumentEmailFailedException;
use App\Service\Email\Exception\IncompleteSmtpSettingsException;
use App\Service\Email\Exception\MissingClientEmailException;
use App\Service\EntityDeleter;
use App\Service\OfferCalculator;
use App\Service\OfferFactory;
use App\Service\OfferToWorkOrderConverter;
use App\Security\Voter\CostVoter;
use App\Service\PartsMarginCalculator;
use App\Service\Pdf\CompanyProfileProvider;
use App\Service\Pdf\PdfRenderer;
use App\Service\Sales\OfferStatusUpdater;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/offers')]
#[IsGranted('ROLE_USER')]
final class OfferController extends AbstractController
{
    #[Route('', name: 'app_offer_index', methods: ['GET'])]
    public function index(Request $request, OfferRepository $repository): Response
    {
        $search = (string) $request->query->get('q', '');
        $statusFilter = (string) $request->query->get('status', '');

        return $this->render('offer/index.html.twig', [
            'offers' => $repository->findForListing($search, $statusFilter),
            'search' => $search,
            'status_filter' => $statusFilter,
            'statuses' => OfferStatus::cases(),
        ]);
    }

    #[Route('/new', name: 'app_offer_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $em, OfferFactory $factory, #[CurrentUser] User $currentUser): Response
    {
        $offer = $factory->createForUser($currentUser);

        $form = $this->createForm(OfferType::class, $offer);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->persist($offer);
            $em->flush();
            $this->addFlash('success', 'flash.offer.created');

            return $this->redirectToRoute('app_offer_show', ['id' => $offer->getId()]);
        }

        return $this->render('offer/new.html.twig', [
            'form' => $form,
            'offer' => $offer,
        ]);
    }

    #[Route('/{id}', name: 'app_offer_show', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function show(Offer $offer, OfferCalculator $calculator, PartsMarginCalculator $partsMarginCalculator): Response
    {
        $calculator->recalculate($offer);

        return $this->render('offer/show.html.twig', [
            'offer' => $offer,
            'parts_margin' => $this->isGranted(CostVoter::VIEW_COSTS) ? $partsMarginCalculator->forOffer($offer) : null,
            'statuses' => OfferStatus::cases(),
        ]);
    }

    #[Route('/{id}/edit', name: 'app_offer_edit', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function edit(Request $request, Offer $offer, EntityManagerInterface $em, OfferCalculator $calculator, OfferStatusUpdater $statusUpdater): Response
    {
        if ($statusUpdater->isLocked($offer)) {
            $this->addFlash('error', 'flash.offer.locked');

            return $this->redirectToRoute('app_offer_show', ['id' => $offer->getId()]);
        }

        $form = $this->createForm(OfferType::class, $offer, [
            'include_items' => true,
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $calculator->recalculate($offer);
            $em->flush();
            $this->addFlash('success', 'flash.offer.updated');

            return $this->redirectToRoute('app_offer_show', ['id' => $offer->getId()]);
        }

        return $this->render('offer/edit.html.twig', [
            'offer' => $offer,
            'form' => $form,
        ]);
    }

    #[Route('/{id}/delete', name: 'app_offer_delete', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function delete(Request $request, Offer $offer, EntityDeleter $deleter): Response
    {
        if ($this->isCsrfTokenValid('delete-offer-' . $offer->getId(), $request->request->get('_token'))) {
            if ($deleter->delete($offer)) {
                $this->addFlash('success', 'flash.offer.deleted');
            } else {
                $this->addFlash('error', 'flash.delete.restricted');

                return $this->redirectToRoute('app_offer_show', ['id' => $offer->getId()]);
            }
        }

        return $this->redirectToRoute('app_offer_index');
    }

    #[Route('/{id}/status', name: 'app_offer_status', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function updateStatus(Request $request, Offer $offer, OfferStatusUpdater $statusUpdater): Response
    {
        if (!$this->isCsrfTokenValid('offer-status-' . $offer->getId(), $request->request->get('_token'))) {
            return $this->redirectToRoute('app_offer_show', ['id' => $offer->getId()]);
        }

        $status = OfferStatus::tryFrom((string) $request->request->get('status'));
        if ($status === null) {
            return $this->redirectToRoute('app_offer_show', ['id' => $offer->getId()]);
        }

        try {
            $statusUpdater->update($offer, $status);
        } catch (\DomainException) {
            $this->addFlash('error', 'flash.offer.locked');

            return $this->redirectToRoute('app_offer_show', ['id' => $offer->getId()]);
        }

        $this->addFlash('success', 'flash.offer.status_updated');

        return $this->redirectToRoute('app_offer_show', ['id' => $offer->getId()]);
    }

    #[Route('/{id}/pdf', name: 'app_offer_pdf', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function pdf(
        Offer $offer,
        OfferCalculator $calculator,
        PdfRenderer $pdfRenderer,
        CompanyProfileProvider $companyProvider,
    ): Response {
        $calculator->recalculate($offer);

        return $pdfRenderer->downloadResponse(
            'pdf/offer.html.twig',
            [
                'offer' => $offer,
                'company' => $companyProvider->getCompany(),
                'logo_path' => $companyProvider->getLogoAbsolutePath(),
                'document_footer_text' => $companyProvider->getDocumentFooterText(),
            ],
            sprintf('offer-%s.pdf', $offer->getNumber() ?? (string) $offer->getId()),
        );
    }

    #[Route('/{id}/email', name: 'app_offer_send_email', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function sendEmail(
        Request $request,
        Offer $offer,
        ClientDocumentMailer $mailer,
        OfferCalculator $calculator,
    ): Response {
        if (!$this->isCsrfTokenValid('offer-email-' . $offer->getId(), $request->request->get('_token'))) {
            return $this->redirectToRoute('app_offer_show', ['id' => $offer->getId()]);
        }

        $calculator->recalculate($offer);

        try {
            $mailer->sendOffer($offer);
            $this->addFlash('success', 'flash.offer.email_sent');
        } catch (MissingClientEmailException) {
            $this->addFlash('error', 'flash.email.client_missing_address');
        } catch (IncompleteSmtpSettingsException) {
            $this->addFlash('error', 'flash.email.smtp_incomplete');
        } catch (DocumentEmailFailedException) {
            $this->addFlash('error', 'flash.email.send_failed');
        }

        return $this->redirectToRoute('app_offer_show', ['id' => $offer->getId()]);
    }

    #[Route('/{id}/convert', name: 'app_offer_convert', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function convert(Request $request, Offer $offer, OfferToWorkOrderConverter $converter, #[CurrentUser] User $currentUser): Response
    {
        if (!$this->isCsrfTokenValid('offer-convert-' . $offer->getId(), $request->request->get('_token'))) {
            return $this->redirectToRoute('app_offer_show', ['id' => $offer->getId()]);
        }

        if (!$offer->isConvertible()) {
            $this->addFlash('error', 'flash.offer.convert_not_allowed');

            return $this->redirectToRoute('app_offer_show', ['id' => $offer->getId()]);
        }

        try {
            $workOrder = $converter->convert($offer, $currentUser);
        } catch (\DomainException) {
            $this->addFlash('error', 'flash.offer.convert_not_allowed');

            return $this->redirectToRoute('app_offer_show', ['id' => $offer->getId()]);
        }

        $this->addFlash('success', 'flash.offer.converted');

        return $this->redirectToRoute('app_work_order_show', ['id' => $workOrder->getId()]);
    }
}
