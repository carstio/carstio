<?php

declare(strict_types=1);

namespace App\Service\Email;

use App\Entity\Sales\Offer;
use App\Entity\Workshop\WorkOrder;
use App\Service\Pdf\CompanyProfileProvider;
use App\Service\Pdf\PdfRenderer;
use App\Settings\CompanyInfo;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Environment;

/**
 * Builds the {@see Email} we send to clients for offers and work orders.
 *
 * Keeps subject/body/attachment construction in one place so the controller
 * and the mailer service stay thin and so the same Twig templates render
 * the body for both documents. PDF generation is delegated to the existing
 * {@see PdfRenderer} so the on-screen download and the email attachment
 * never drift apart.
 */
class DocumentEmailFactory
{
    public function __construct(
        private readonly Environment $twig,
        private readonly PdfRenderer $pdfRenderer,
        private readonly CompanyProfileProvider $companyProvider,
        private readonly TranslatorInterface $translator,
    ) {
    }

    public function buildOfferEmail(Offer $offer, Address $from): Email
    {
        $client = $offer->getClient();
        $clientEmail = $client?->getEmail();
        if ($clientEmail === null || $clientEmail === '') {
            throw new \LogicException('Client email must be present before building the email.');
        }

        $company = $this->companyProvider->getCompany();
        $companyName = $company->companyName ?? $from->getName();
        $number = $offer->getNumber() ?? (string) $offer->getId();

        $subject = $this->translator->trans('email.offer.subject', [
            '%number%' => $number,
            '%company%' => $companyName,
        ]);

        $context = [
            'offer' => $offer,
            'company' => $company,
            'subject' => $subject,
        ];

        $email = (new Email())
            ->from($from)
            ->to(new Address($clientEmail, (string) $client?->getName()))
            ->subject($subject)
            ->text($this->twig->render('emails/offer.txt.twig', $context))
            ->html($this->twig->render('emails/offer.html.twig', $context));

        $pdf = $this->pdfRenderer->renderToString('pdf/offer.html.twig', [
            'offer' => $offer,
            'company' => $company,
            'logo_path' => $this->companyProvider->getLogoAbsolutePath(),
            'document_footer_text' => $this->companyProvider->getDocumentFooterText(),
        ]);

        $email->attach($pdf, $this->offerAttachmentName($offer), 'application/pdf');

        return $email;
    }

    public function buildWorkOrderEmail(WorkOrder $workOrder, Address $from): Email
    {
        $client = $workOrder->getClient();
        $clientEmail = $client?->getEmail();
        if ($clientEmail === null || $clientEmail === '') {
            throw new \LogicException('Client email must be present before building the email.');
        }

        $company = $this->companyProvider->getCompany();
        $companyName = $company->companyName ?? $from->getName();
        $number = $workOrder->getNumber() ?? (string) $workOrder->getId();

        $subject = $this->translator->trans('email.work_order.subject', [
            '%number%' => $number,
            '%company%' => $companyName,
        ]);

        $context = [
            'work_order' => $workOrder,
            'company' => $company,
            'subject' => $subject,
        ];

        $email = (new Email())
            ->from($from)
            ->to(new Address($clientEmail, (string) $client?->getName()))
            ->subject($subject)
            ->text($this->twig->render('emails/work_order.txt.twig', $context))
            ->html($this->twig->render('emails/work_order.html.twig', $context));

        $pdf = $this->pdfRenderer->renderToString('pdf/work_order.html.twig', [
            'work_order' => $workOrder,
            'company' => $company,
            'logo_path' => $this->companyProvider->getLogoAbsolutePath(),
            'document_footer_text' => $this->companyProvider->getDocumentFooterText(),
        ]);

        $email->attach($pdf, $this->workOrderAttachmentName($workOrder), 'application/pdf');

        return $email;
    }

    private function offerAttachmentName(Offer $offer): string
    {
        return $this->sanitize(sprintf('offer-%s.pdf', $offer->getNumber() ?? (string) $offer->getId()));
    }

    private function workOrderAttachmentName(WorkOrder $workOrder): string
    {
        return $this->sanitize(sprintf('work-order-%s.pdf', $workOrder->getNumber() ?? (string) $workOrder->getId()));
    }

    private function sanitize(string $filename): string
    {
        $filename = preg_replace('/[^A-Za-z0-9._-]+/', '-', $filename) ?? 'document.pdf';
        $filename = trim($filename, '-');

        return $filename !== '' ? $filename : 'document.pdf';
    }
}
