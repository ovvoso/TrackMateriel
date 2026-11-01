<?php

declare(strict_types=1);

namespace App\Inventory\Entity;

use App\Inventory\Enum\AssetCondition;
use App\Inventory\Enum\AssetStatus;
use App\Inventory\Repository\AssetRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

/**
 * Un équipement du parc. Il n'est jamais supprimé : en fin de vie il est
 * réformé, ce qui conserve son historique.
 */
#[ORM\Entity(repositoryClass: AssetRepository::class)]
#[ORM\Table(name: 'inventory_asset')]
#[ORM\Index(name: 'idx_asset_status', columns: ['status'])]
class Asset
{
    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME, unique: true)]
    private Uuid $id;

    #[ORM\Column(length: 20, unique: true)]
    private string $code;

    #[ORM\Column(length: 150)]
    private string $name;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'RESTRICT')]
    private Category $category;

    #[ORM\Column(length: 80, nullable: true)]
    private ?string $brand = null;

    #[ORM\Column(length: 80, nullable: true)]
    private ?string $model = null;

    #[ORM\Column(length: 100, unique: true, nullable: true)]
    private ?string $serialNumber = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(onDelete: 'RESTRICT')]
    private ?Location $location = null;

    #[ORM\Column(length: 20, enumType: AssetStatus::class)]
    private AssetStatus $status = AssetStatus::Available;

    #[ORM\Column(name: 'physical_condition', length: 16, enumType: AssetCondition::class)]
    private AssetCondition $condition = AssetCondition::Good;

    #[ORM\Column(type: Types::DATE_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $purchasedAt = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 12, scale: 2, nullable: true)]
    private ?string $purchasePrice = null;

    #[ORM\Column(type: Types::DATE_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $warrantyEndsAt = null;

    #[ORM\Column(length: 120, nullable: true)]
    private ?string $supplier = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $notes = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $updatedAt;

    public function __construct(string $code, string $name, Category $category)
    {
        $this->id = Uuid::v7();
        $this->code = $code;
        $this->name = $name;
        $this->category = $category;
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = $this->createdAt;
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getCode(): string
    {
        return $this->code;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }

    public function getCategory(): Category
    {
        return $this->category;
    }

    public function setCategory(Category $category): void
    {
        $this->category = $category;
    }

    public function getBrand(): ?string
    {
        return $this->brand;
    }

    public function setBrand(?string $brand): void
    {
        $this->brand = $brand;
    }

    public function getModel(): ?string
    {
        return $this->model;
    }

    public function setModel(?string $model): void
    {
        $this->model = $model;
    }

    public function getSerialNumber(): ?string
    {
        return $this->serialNumber;
    }

    public function setSerialNumber(?string $serialNumber): void
    {
        $this->serialNumber = $serialNumber;
    }

    public function getLocation(): ?Location
    {
        return $this->location;
    }

    public function setLocation(?Location $location): void
    {
        $this->location = $location;
    }

    public function getStatus(): AssetStatus
    {
        return $this->status;
    }

    /**
     * Réservé au composant Workflow : le statut ne change que par une transition.
     */
    public function setStatus(AssetStatus $status): void
    {
        $this->status = $status;
    }

    public function getCondition(): AssetCondition
    {
        return $this->condition;
    }

    public function setCondition(AssetCondition $condition): void
    {
        $this->condition = $condition;
    }

    public function getPurchasedAt(): ?\DateTimeImmutable
    {
        return $this->purchasedAt;
    }

    public function setPurchasedAt(?\DateTimeImmutable $purchasedAt): void
    {
        $this->purchasedAt = $purchasedAt;
    }

    public function getPurchasePrice(): ?string
    {
        return $this->purchasePrice;
    }

    public function setPurchasePrice(?string $purchasePrice): void
    {
        $this->purchasePrice = $purchasePrice;
    }

    public function getWarrantyEndsAt(): ?\DateTimeImmutable
    {
        return $this->warrantyEndsAt;
    }

    public function setWarrantyEndsAt(?\DateTimeImmutable $warrantyEndsAt): void
    {
        $this->warrantyEndsAt = $warrantyEndsAt;
    }

    public function isUnderWarranty(\DateTimeInterface $today): bool
    {
        return null !== $this->warrantyEndsAt && $this->warrantyEndsAt >= $today;
    }

    public function getSupplier(): ?string
    {
        return $this->supplier;
    }

    public function setSupplier(?string $supplier): void
    {
        $this->supplier = $supplier;
    }

    public function getNotes(): ?string
    {
        return $this->notes;
    }

    public function setNotes(?string $notes): void
    {
        $this->notes = $notes;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function touch(\DateTimeImmutable $at): void
    {
        $this->updatedAt = $at;
    }

    public function isRetired(): bool
    {
        return AssetStatus::Retired === $this->status;
    }
}
