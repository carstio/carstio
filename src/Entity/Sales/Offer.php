<?php

namespace App\Entity\Sales;

use App\Entity\Car\Car;
use App\Entity\Client\Client;
use App\Entity\User;
use App\Entity\Workshop\WorkOrder;
use App\Enum\Currency;
use App\Enum\OfferStatus;
use App\Repository\Sales\OfferRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: OfferRepository::class)]
class Offer
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 50, unique: true)]
    private ?string $number = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    #[Assert\NotNull]
    private ?Client $client = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    #[Assert\NotNull]
    private ?Car $car = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?User $createdBy = null;

    #[ORM\Column(length: 30, enumType: OfferStatus::class)]
    private OfferStatus $status = OfferStatus::Draft;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $title = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $notes = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $validUntil = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2)]
    private string $subtotalLabor = '0.00';

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2)]
    private string $subtotalParts = '0.00';

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2)]
    private string $discountAmount = '0.00';

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2)]
    private string $taxAmount = '0.00';

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2)]
    private string $totalAmount = '0.00';

    /**
     * Currency snapshot taken at offer creation. Kept even if the workshop
     * later changes its default currency, so historical documents stay
     * accurate.
     */
    #[ORM\Column(length: 3, options: ['default' => 'EUR'])]
    #[Assert\NotBlank]
    #[Assert\Choice(callback: [Currency::class, 'values'])]
    private string $currencyCode = Currency::DEFAULT->value;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $convertedAt = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?WorkOrder $workOrder = null;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column]
    private \DateTimeImmutable $updatedAt;

    /**
     * @var Collection<int, OfferItem>
     */
    #[ORM\OneToMany(targetEntity: OfferItem::class, mappedBy: 'offer', cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['sortOrder' => 'ASC'])]
    private Collection $items;

    public function __construct()
    {
        $this->items = new ArrayCollection();
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getNumber(): ?string
    {
        return $this->number;
    }

    public function setNumber(string $number): static
    {
        $this->number = $number;

        return $this;
    }

    public function getClient(): ?Client
    {
        return $this->client;
    }

    public function setClient(?Client $client): static
    {
        $this->client = $client;

        return $this;
    }

    public function getCar(): ?Car
    {
        return $this->car;
    }

    public function setCar(?Car $car): static
    {
        $this->car = $car;

        return $this;
    }

    public function getCreatedBy(): ?User
    {
        return $this->createdBy;
    }

    public function setCreatedBy(?User $createdBy): static
    {
        $this->createdBy = $createdBy;

        return $this;
    }

    public function getStatus(): OfferStatus
    {
        return $this->status;
    }

    public function setStatus(OfferStatus $status): static
    {
        $this->status = $status;

        return $this;
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function setTitle(?string $title): static
    {
        $this->title = $title;

        return $this;
    }

    public function getNotes(): ?string
    {
        return $this->notes;
    }

    public function setNotes(?string $notes): static
    {
        $this->notes = $notes;

        return $this;
    }

    public function getValidUntil(): ?\DateTimeImmutable
    {
        return $this->validUntil;
    }

    public function setValidUntil(?\DateTimeImmutable $validUntil): static
    {
        $this->validUntil = $validUntil;

        return $this;
    }

    public function getSubtotalLabor(): string
    {
        return $this->subtotalLabor;
    }

    public function setSubtotalLabor(string $subtotalLabor): static
    {
        $this->subtotalLabor = $subtotalLabor;

        return $this;
    }

    public function getSubtotalParts(): string
    {
        return $this->subtotalParts;
    }

    public function setSubtotalParts(string $subtotalParts): static
    {
        $this->subtotalParts = $subtotalParts;

        return $this;
    }

    public function getDiscountAmount(): string
    {
        return $this->discountAmount;
    }

    public function setDiscountAmount(string $discountAmount): static
    {
        $this->discountAmount = $discountAmount;

        return $this;
    }

    public function getTaxAmount(): string
    {
        return $this->taxAmount;
    }

    public function setTaxAmount(string $taxAmount): static
    {
        $this->taxAmount = $taxAmount;

        return $this;
    }

    public function getTotalAmount(): string
    {
        return $this->totalAmount;
    }

    public function setTotalAmount(string $totalAmount): static
    {
        $this->totalAmount = $totalAmount;

        return $this;
    }

    public function getCurrencyCode(): string
    {
        return $this->currencyCode;
    }

    public function setCurrencyCode(string $currencyCode): static
    {
        $this->currencyCode = $currencyCode;

        return $this;
    }

    public function getConvertedAt(): ?\DateTimeImmutable
    {
        return $this->convertedAt;
    }

    public function setConvertedAt(?\DateTimeImmutable $convertedAt): static
    {
        $this->convertedAt = $convertedAt;

        return $this;
    }

    public function getWorkOrder(): ?WorkOrder
    {
        return $this->workOrder;
    }

    public function setWorkOrder(?WorkOrder $workOrder): static
    {
        $this->workOrder = $workOrder;

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(\DateTimeImmutable $updatedAt): static
    {
        $this->updatedAt = $updatedAt;

        return $this;
    }

    /**
     * @return Collection<int, OfferItem>
     */
    public function getItems(): Collection
    {
        return $this->items;
    }

    public function addItem(OfferItem $item): static
    {
        if (!$this->items->contains($item)) {
            $this->items->add($item);
            $item->setOffer($this);
        }

        return $this;
    }

    public function removeItem(OfferItem $item): static
    {
        if ($this->items->removeElement($item)) {
            if ($item->getOffer() === $this) {
                $item->setOffer(null);
            }
        }

        return $this;
    }

    public function isConvertible(): bool
    {
        return $this->status === OfferStatus::Approved && $this->workOrder === null;
    }
}
