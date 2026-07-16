<?php

namespace App\Entity\Attachment;

use App\Entity\User;
use App\Enum\AttachmentCategory;
use App\Enum\AttachmentOwnerType;
use App\Repository\Attachment\AttachmentRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Generic file attachment. Polymorphic ownership is expressed via
 * (ownerType, ownerId) instead of a per-owner join table so future
 * owners (Offer, Car, Client, Payment, Inspection, Invoice) can plug in
 * without a schema change.
 */
#[ORM\Entity(repositoryClass: AttachmentRepository::class)]
#[ORM\Table(name: 'attachment')]
#[ORM\Index(name: 'idx_attachment_owner', columns: ['owner_type', 'owner_id'])]
class Attachment
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 32, enumType: AttachmentOwnerType::class)]
    private AttachmentOwnerType $ownerType;

    #[ORM\Column]
    private int $ownerId;

    #[ORM\Column(length: 32, enumType: AttachmentCategory::class)]
    private AttachmentCategory $category = AttachmentCategory::Other;

    #[ORM\Column(length: 255)]
    private string $originalFilename;

    #[ORM\Column(length: 128)]
    private string $storedFilename;

    /**
     * Path relative to the attachment storage root (no leading slash).
     * Example: "work_order/2026/04/6f/6f8a9c2d.pdf".
     */
    #[ORM\Column(length: 512)]
    private string $storagePath;

    #[ORM\Column(length: 128)]
    private string $mimeType;

    #[ORM\Column]
    private int $size;

    #[ORM\Column(length: 16, nullable: true)]
    private ?string $extension = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $description = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?User $uploadedBy = null;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column]
    private \DateTimeImmutable $updatedAt;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getOwnerType(): AttachmentOwnerType
    {
        return $this->ownerType;
    }

    public function setOwnerType(AttachmentOwnerType $ownerType): static
    {
        $this->ownerType = $ownerType;

        return $this;
    }

    public function getOwnerId(): int
    {
        return $this->ownerId;
    }

    public function setOwnerId(int $ownerId): static
    {
        $this->ownerId = $ownerId;

        return $this;
    }

    public function getCategory(): AttachmentCategory
    {
        return $this->category;
    }

    public function setCategory(AttachmentCategory $category): static
    {
        $this->category = $category;

        return $this;
    }

    public function getOriginalFilename(): string
    {
        return $this->originalFilename;
    }

    public function setOriginalFilename(string $originalFilename): static
    {
        $this->originalFilename = $originalFilename;

        return $this;
    }

    public function getStoredFilename(): string
    {
        return $this->storedFilename;
    }

    public function setStoredFilename(string $storedFilename): static
    {
        $this->storedFilename = $storedFilename;

        return $this;
    }

    public function getStoragePath(): string
    {
        return $this->storagePath;
    }

    public function setStoragePath(string $storagePath): static
    {
        $this->storagePath = $storagePath;

        return $this;
    }

    public function getMimeType(): string
    {
        return $this->mimeType;
    }

    public function setMimeType(string $mimeType): static
    {
        $this->mimeType = $mimeType;

        return $this;
    }

    public function getSize(): int
    {
        return $this->size;
    }

    public function setSize(int $size): static
    {
        $this->size = $size;

        return $this;
    }

    public function getExtension(): ?string
    {
        return $this->extension;
    }

    public function setExtension(?string $extension): static
    {
        $this->extension = $extension;

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

    public function getUploadedBy(): ?User
    {
        return $this->uploadedBy;
    }

    public function setUploadedBy(?User $uploadedBy): static
    {
        $this->uploadedBy = $uploadedBy;

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

    public function isInlineSafe(): bool
    {
        return in_array($this->mimeType, [
            'image/jpeg',
            'image/png',
            'image/webp',
            'application/pdf',
        ], true);
    }
}
