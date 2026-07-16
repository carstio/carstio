<?php

namespace App\Entity\Sales;

use App\Entity\Part\Part;
use App\Enum\OfferItemType;
use App\Repository\Sales\OfferItemRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: OfferItemRepository::class)]
class OfferItem
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'items')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Offer $offer = null;

    #[ORM\Column(length: 20, enumType: OfferItemType::class)]
    private ?OfferItemType $type = null;

    #[ORM\Column(length: 255)]
    private ?string $name = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $description = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2)]
    private string $quantity = '1.00';

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2)]
    private string $unitPrice = '0.00';

    /**
     * Internal purchase/cost price per unit. Optional; only meaningful for
     * part-type lines so the workshop can track parts margin without
     * exposing cost in client-facing PDFs.
     */
    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2, nullable: true)]
    private ?string $costPrice = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2)]
    private string $discountAmount = '0.00';

    #[ORM\Column(type: Types::DECIMAL, precision: 5, scale: 2)]
    private string $taxRate = '0.00';

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2)]
    private string $lineTotal = '0.00';

    #[ORM\ManyToOne]
    private ?Part $part = null;

    #[ORM\Column]
    private int $sortOrder = 0;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getOffer(): ?Offer
    {
        return $this->offer;
    }

    public function setOffer(?Offer $offer): static
    {
        $this->offer = $offer;

        return $this;
    }

    public function getType(): ?OfferItemType
    {
        return $this->type;
    }

    public function setType(OfferItemType $type): static
    {
        $this->type = $type;

        return $this;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;

        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): static
    {
        $this->description = $description;

        return $this;
    }

    public function getQuantity(): string
    {
        return $this->quantity;
    }

    public function setQuantity(string $quantity): static
    {
        $this->quantity = $quantity;

        return $this;
    }

    public function getUnitPrice(): string
    {
        return $this->unitPrice;
    }

    public function setUnitPrice(string $unitPrice): static
    {
        $this->unitPrice = $unitPrice;

        return $this;
    }

    public function getCostPrice(): ?string
    {
        return $this->costPrice;
    }

    public function setCostPrice(?string $costPrice): static
    {
        $this->costPrice = $costPrice;

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

    public function getTaxRate(): string
    {
        return $this->taxRate;
    }

    public function setTaxRate(string $taxRate): static
    {
        $this->taxRate = $taxRate;

        return $this;
    }

    public function getLineTotal(): string
    {
        return $this->lineTotal;
    }

    public function setLineTotal(string $lineTotal): static
    {
        $this->lineTotal = $lineTotal;

        return $this;
    }

    public function getPart(): ?Part
    {
        return $this->part;
    }

    public function setPart(?Part $part): static
    {
        $this->part = $part;

        return $this;
    }

    public function getSortOrder(): int
    {
        return $this->sortOrder;
    }

    public function setSortOrder(int $sortOrder): static
    {
        $this->sortOrder = $sortOrder;

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
