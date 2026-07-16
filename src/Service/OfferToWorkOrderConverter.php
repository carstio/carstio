<?php

namespace App\Service;

use App\Entity\Sales\Offer;
use App\Entity\User;
use App\Entity\Workshop\WorkOrder;
use App\Entity\Workshop\WorkOrderItem;
use App\Enum\OfferStatus;
use App\Enum\WorkOrderItemType;
use Doctrine\ORM\EntityManagerInterface;

class OfferToWorkOrderConverter
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly WorkOrderFactory $workOrderFactory,
        private readonly WorkOrderCalculator $workOrderCalculator,
    ) {
    }

    public function convert(Offer $offer, User $user): WorkOrder
    {
        if (!$offer->isConvertible()) {
            throw new \DomainException('Only approved offers without an existing work order can be converted.');
        }

        $workOrder = $this->workOrderFactory->createForUser($user);
        $workOrder->setClient($offer->getClient());
        $workOrder->setCar($offer->getCar());
        $workOrder->setCurrencyCode($offer->getCurrencyCode());
        $workOrder->setSource('Offer ' . $offer->getNumber());
        $workOrder->setComplaint($this->buildComplaint($offer));

        if ($offer->getNotes() !== null) {
            $workOrder->setNotes($offer->getNotes());
        }

        foreach ($offer->getItems() as $offerItem) {
            $workOrderItem = new WorkOrderItem();
            $workOrderItem->setType(WorkOrderItemType::from($offerItem->getType()->value));
            $workOrderItem->setName($offerItem->getName());
            $workOrderItem->setDescription($offerItem->getDescription());
            $workOrderItem->setQuantity($offerItem->getQuantity());
            $workOrderItem->setUnitPrice($offerItem->getUnitPrice());
            $workOrderItem->setCostPrice($offerItem->getCostPrice());
            $workOrderItem->setDiscountAmount($offerItem->getDiscountAmount());
            $workOrderItem->setTaxRate($offerItem->getTaxRate());
            $workOrderItem->setPart($offerItem->getPart());
            $workOrderItem->setSortOrder($offerItem->getSortOrder());
            $workOrder->addItem($workOrderItem);
        }

        $this->workOrderCalculator->recalculate($workOrder);

        $this->entityManager->persist($workOrder);

        $offer->setStatus(OfferStatus::Converted);
        $offer->setConvertedAt(new \DateTimeImmutable());
        $offer->setWorkOrder($workOrder);
        $offer->setUpdatedAt(new \DateTimeImmutable());

        $this->entityManager->flush();

        return $workOrder;
    }

    private function buildComplaint(Offer $offer): string
    {
        if ($offer->getTitle() !== null && trim($offer->getTitle()) !== '') {
            return $offer->getTitle();
        }

        return 'Converted from offer ' . $offer->getNumber();
    }
}
