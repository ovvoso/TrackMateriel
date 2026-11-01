<?php

declare(strict_types=1);

namespace App\Inventory\Entity;

use App\Identity\Entity\User;
use App\Inventory\Enum\AssetEventType;
use App\Inventory\Repository\AssetEventRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

/**
 * Entrée du journal d'un équipement. Le journal est en ajout seul : aucune
 * entrée n'est modifiée ni supprimée.
 */
#[ORM\Entity(repositoryClass: AssetEventRepository::class, readOnly: true)]
#[ORM\Table(name: 'inventory_asset_event')]
#[ORM\Index(name: 'idx_asset_event_occurred', columns: ['asset_id', 'occurred_at'])]
class AssetEvent
{
    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME, unique: true)]
    private Uuid $id;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Asset $asset;

    #[ORM\Column(length: 20, enumType: AssetEventType::class)]
    private AssetEventType $type;

    #[ORM\Column(length: 255)]
    private string $summary;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(onDelete: 'SET NULL')]
    private ?User $actor;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $occurredAt;

    public function __construct(Asset $asset, AssetEventType $type, string $summary, ?User $actor, \DateTimeImmutable $occurredAt)
    {
        $this->id = Uuid::v7();
        $this->asset = $asset;
        $this->type = $type;
        $this->summary = $summary;
        $this->actor = $actor;
        $this->occurredAt = $occurredAt;
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getAsset(): Asset
    {
        return $this->asset;
    }

    public function getType(): AssetEventType
    {
        return $this->type;
    }

    public function getSummary(): string
    {
        return $this->summary;
    }

    public function getActor(): ?User
    {
        return $this->actor;
    }

    public function getOccurredAt(): \DateTimeImmutable
    {
        return $this->occurredAt;
    }
}
