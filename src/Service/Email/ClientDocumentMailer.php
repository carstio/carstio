<?php

declare(strict_types=1);

namespace App\Service\Email;

use App\Entity\Sales\Offer;
use App\Entity\Workshop\WorkOrder;
use App\Service\Email\Exception\DocumentEmailFailedException;
use App\Service\Email\Exception\IncompleteSmtpSettingsException;
use App\Service\Email\Exception\MissingClientEmailException;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;

/**
 * Orchestrates "send document to client" for offers and work orders.
 *
 * Each call:
 *   1. checks the client has an email,
 *   2. asks {@see SmtpMailerFactory} for a fresh SMTP-backed mailer
 *      (which validates the SMTP settings),
 *   3. lets {@see DocumentEmailFactory} compose the {@see \Symfony\Component\Mime\Email},
 *   4. sends, and translates transport failures into a generic
 *      {@see DocumentEmailFailedException} so callers can show a
 *      non-technical error message.
 *
 * SMTP credentials and full transport errors are only ever written to the
 * application logger - never bubble up to the controller's flash.
 */
class ClientDocumentMailer
{
    public function __construct(
        private readonly SmtpMailerFactory $mailerFactory,
        private readonly DocumentEmailFactory $emailFactory,
        private readonly LoggerInterface $logger = new NullLogger(),
    ) {
    }

    public function sendOffer(Offer $offer): void
    {
        $client = $offer->getClient();
        if ($client === null || ($client->getEmail() ?? '') === '') {
            throw new MissingClientEmailException('Client email is required to send an offer.');
        }

        $from = $this->mailerFactory->fromAddress();
        $email = $this->emailFactory->buildOfferEmail($offer, $from);

        $this->dispatch($email, 'offer', $offer->getId(), $client->getEmail());
    }

    public function sendWorkOrder(WorkOrder $workOrder): void
    {
        $client = $workOrder->getClient();
        if ($client === null || ($client->getEmail() ?? '') === '') {
            throw new MissingClientEmailException('Client email is required to send a work order.');
        }

        $from = $this->mailerFactory->fromAddress();
        $email = $this->emailFactory->buildWorkOrderEmail($workOrder, $from);

        $this->dispatch($email, 'work_order', $workOrder->getId(), $client->getEmail());
    }

    private function dispatch(
        \Symfony\Component\Mime\Email $email,
        string $documentType,
        ?int $documentId,
        ?string $recipient,
    ): void {
        try {
            $this->mailerFactory->create()->send($email);
        } catch (IncompleteSmtpSettingsException $e) {
            // Bubbles up unchanged; the controller turns this into a
            // translated "settings incomplete" flash.
            throw $e;
        } catch (TransportExceptionInterface $e) {
            $this->logger->error('Document email transport failed', [
                'document_type' => $documentType,
                'document_id' => $documentId,
                'recipient' => $recipient,
                'exception' => $e,
            ]);
            throw new DocumentEmailFailedException('Failed to send the document email.', 0, $e);
        }
    }
}
