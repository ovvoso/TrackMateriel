<?php

declare(strict_types=1);

namespace App\Inventory\Form;

use App\Inventory\Entity\Asset;
use App\Inventory\Entity\Category;
use App\Inventory\Entity\Location;
use App\Inventory\Enum\AssetCondition;
use App\Inventory\Validator\UniqueSerialNumber;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Saisie d'une fiche équipement. Le formulaire travaille sur cet objet ;
 * l'entité n'est modifiée qu'une fois la saisie validée.
 */
#[UniqueSerialNumber]
final class AssetInput
{
    /** Équipement modifié, null à la création */
    public ?Uuid $assetId = null;

    #[Assert\NotBlank(message: 'Indiquez la désignation de l’équipement.')]
    #[Assert\Length(max: 150)]
    public ?string $name = null;

    #[Assert\NotNull(message: 'Choisissez une catégorie.')]
    public ?Category $category = null;

    #[Assert\Length(max: 80)]
    public ?string $brand = null;

    #[Assert\Length(max: 80)]
    public ?string $model = null;

    #[Assert\Length(max: 100)]
    public ?string $serialNumber = null;

    public ?Location $location = null;

    public AssetCondition $condition = AssetCondition::Good;

    #[Assert\LessThanOrEqual('today', message: 'La date d’achat ne peut pas être dans le futur.')]
    public ?\DateTimeImmutable $purchasedAt = null;

    #[Assert\PositiveOrZero]
    public ?string $purchasePrice = null;

    #[Assert\GreaterThanOrEqual(propertyPath: 'purchasedAt', message: 'La fin de garantie doit suivre la date d’achat.')]
    public ?\DateTimeImmutable $warrantyEndsAt = null;

    #[Assert\Length(max: 120)]
    public ?string $supplier = null;

    #[Assert\Length(max: 2000)]
    public ?string $notes = null;

    public static function fromAsset(Asset $asset): self
    {
        $input = new self();
        $input->assetId = $asset->getId();
        $input->name = $asset->getName();
        $input->category = $asset->getCategory();
        $input->brand = $asset->getBrand();
        $input->model = $asset->getModel();
        $input->serialNumber = $asset->getSerialNumber();
        $input->location = $asset->getLocation();
        $input->condition = $asset->getCondition();
        $input->purchasedAt = $asset->getPurchasedAt();
        $input->purchasePrice = $asset->getPurchasePrice();
        $input->warrantyEndsAt = $asset->getWarrantyEndsAt();
        $input->supplier = $asset->getSupplier();
        $input->notes = $asset->getNotes();

        return $input;
    }

    /**
     * Valeurs comparables champ par champ, pour détecter ce qui a changé.
     *
     * @return array<string, string|null>
     */
    public function fingerprint(): array
    {
        return [
            'name' => $this->name,
            'category' => $this->category?->getId()->toRfc4122(),
            'brand' => $this->brand,
            'model' => $this->model,
            'serialNumber' => self::normalizeSerial($this->serialNumber),
            'condition' => $this->condition->value,
            'purchasedAt' => $this->purchasedAt?->format('Y-m-d'),
            'purchasePrice' => null === $this->purchasePrice ? null : number_format((float) $this->purchasePrice, 2, '.', ''),
            'warrantyEndsAt' => $this->warrantyEndsAt?->format('Y-m-d'),
            'supplier' => $this->supplier,
            'notes' => $this->notes,
        ];
    }

    public static function normalizeSerial(?string $serial): ?string
    {
        $serial = null === $serial ? '' : trim($serial);

        return '' === $serial ? null : $serial;
    }
}
