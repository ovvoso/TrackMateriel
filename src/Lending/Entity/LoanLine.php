<?php

declare(strict_types=1);

namespace App\Lending\Entity;

use App\Identity\Entity\User;
use App\Inventory\Entity\Asset;
use App\Inventory\Enum\AssetCondition;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

/**
 * Un équipement dans un prêt, avec son état au départ et au retour.
 */
#[ORM\Entity]
#[ORM\Table(name: 'lending_loan_line')]
// Garantie en base : un équipement ne peut figurer que dans une seule ligne non rendue
#[ORM\UniqueConstraint(name: 'uniq_loan_line_open_asset', columns: ['asset_id'], options: ['where' => '(returned_at IS NULL)'])]
class LoanLine
{
    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME, unique: true)]
    private Uuid $id;

    #[ORM\ManyToOne(inversedBy: 'lines')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Loan $loan;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'RESTRICT')]
    private Asset $asset;

    #[ORM\Column(length: 16, enumType: AssetCondition::class)]
    private AssetCondition $conditionOut;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $returnedAt = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(onDelete: 'SET NULL')]
    private ?User $receivedBy = null;

    #[ORM\Column(length: 16, nullable: true, enumType: AssetCondition::class)]
    private ?AssetCondition $conditionIn = null;

    public function __construct(Loan $loan, Asset $asset)
    {
        $this->id = Uuid::v7();
        $this->loan = $loan;
        $this->asset = $asset;
        $this->conditionOut = $asset->getCondition();
        $loan->addLine($this);
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getLoan(): Loan
    {
        return $this->loan;
    }

    public function getAsset(): Asset
    {
        return $this->asset;
    }

    public function getConditionOut(): AssetCondition
    {
        return $this->conditionOut;
    }

    public function isReturned(): bool
    {
        return null !== $this->returnedAt;
    }

    public function getReturnedAt(): ?\DateTimeImmutable
    {
        return $this->returnedAt;
    }

    public function getReceivedBy(): ?User
    {
        return $this->receivedBy;
    }

    public function getConditionIn(): ?AssetCondition
    {
        return $this->conditionIn;
    }

    public function markReturned(\DateTimeImmutable $at, AssetCondition $condition, ?User $receivedBy): void
    {
        if ($this->isReturned()) {
            throw new \LogicException(\sprintf('%s a déjà été rendu.', $this->asset->getCode()));
        }

        $this->returnedAt = $at;
        $this->conditionIn = $condition;
        $this->receivedBy = $receivedBy;
    }
}
