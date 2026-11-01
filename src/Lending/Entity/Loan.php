<?php

declare(strict_types=1);

namespace App\Lending\Entity;

use App\Identity\Entity\User;
use App\Lending\Enum\LoanState;
use App\Lending\Model\Borrower;
use App\Lending\Repository\LoanRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

/**
 * Un prêt : un ou plusieurs équipements confiés à un emprunteur jusqu'à une
 * date de retour. Les équipements peuvent être rendus en plusieurs fois ;
 * le prêt est clos au retour du dernier.
 */
#[ORM\Entity(repositoryClass: LoanRepository::class)]
#[ORM\Table(name: 'lending_loan')]
#[ORM\Index(name: 'idx_loan_open_due', columns: ['closed_at', 'due_at'])]
class Loan
{
    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME, unique: true)]
    private Uuid $id;

    #[ORM\Column(length: 20, unique: true)]
    private string $reference;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(onDelete: 'SET NULL')]
    private ?User $borrowerAccount;

    /** Nom copié au moment du prêt, conservé même si le compte disparaît */
    #[ORM\Column(length: 160)]
    private string $borrowerName;

    #[ORM\Column(length: 80, nullable: true)]
    private ?string $borrowerGroup;

    #[ORM\Column(length: 160, nullable: true)]
    private ?string $purpose;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $lentAt;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(onDelete: 'SET NULL')]
    private ?User $lentBy;

    #[ORM\Column(type: Types::DATE_IMMUTABLE)]
    private \DateTimeImmutable $dueAt;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $closedAt = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $notes;

    /**
     * @var Collection<int, LoanLine>
     */
    #[ORM\OneToMany(targetEntity: LoanLine::class, mappedBy: 'loan', cascade: ['persist'])]
    #[ORM\OrderBy(['id' => 'ASC'])]
    private Collection $lines;

    public function __construct(
        string $reference,
        Borrower $borrower,
        \DateTimeImmutable $lentAt,
        \DateTimeImmutable $dueAt,
        ?User $lentBy,
        ?string $purpose = null,
        ?string $notes = null,
    ) {
        $this->id = Uuid::v7();
        $this->reference = $reference;
        $this->borrowerAccount = $borrower->account;
        $this->borrowerName = $borrower->name;
        $this->borrowerGroup = $borrower->group;
        $this->lentAt = $lentAt;
        $this->dueAt = $dueAt;
        $this->lentBy = $lentBy;
        $this->purpose = $purpose;
        $this->notes = $notes;
        $this->lines = new ArrayCollection();
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getReference(): string
    {
        return $this->reference;
    }

    public function getBorrower(): Borrower
    {
        return Borrower::restore($this->borrowerAccount, $this->borrowerName, $this->borrowerGroup);
    }

    public function getPurpose(): ?string
    {
        return $this->purpose;
    }

    public function getLentAt(): \DateTimeImmutable
    {
        return $this->lentAt;
    }

    public function getLentBy(): ?User
    {
        return $this->lentBy;
    }

    public function getDueAt(): \DateTimeImmutable
    {
        return $this->dueAt;
    }

    public function extendTo(\DateTimeImmutable $dueAt): void
    {
        if ($this->isClosed()) {
            throw new \LogicException('Un prêt clos ne peut pas être prolongé.');
        }
        if ($dueAt <= $this->dueAt) {
            throw new \InvalidArgumentException('La nouvelle date de retour doit suivre la date actuelle.');
        }

        $this->dueAt = $dueAt;
    }

    public function getClosedAt(): ?\DateTimeImmutable
    {
        return $this->closedAt;
    }

    public function isClosed(): bool
    {
        return null !== $this->closedAt;
    }

    public function getNotes(): ?string
    {
        return $this->notes;
    }

    /**
     * @return Collection<int, LoanLine>
     */
    public function getLines(): Collection
    {
        return $this->lines;
    }

    public function addLine(LoanLine $line): void
    {
        $this->lines->add($line);
    }

    /**
     * @return list<LoanLine>
     */
    public function getOutstandingLines(): array
    {
        return array_values($this->lines->filter(static fn (LoanLine $line) => !$line->isReturned())->toArray());
    }

    /**
     * Clôt le prêt si tout le matériel est revenu.
     */
    public function closeIfComplete(\DateTimeImmutable $at): void
    {
        if (!$this->isClosed() && [] === $this->getOutstandingLines()) {
            $this->closedAt = $at;
        }
    }

    public function getState(\DateTimeInterface $today): LoanState
    {
        return match (true) {
            $this->isClosed() => LoanState::Returned,
            $this->dueAt < $today => LoanState::Overdue,
            default => LoanState::Ongoing,
        };
    }

    /**
     * Jours de retard (positif) ou jours restants (négatif) par rapport à la date prévue.
     */
    public function daysLate(\DateTimeInterface $today): int
    {
        $days = (int) $this->dueAt->diff(\DateTimeImmutable::createFromInterface($today)->setTime(0, 0))->format('%r%a');

        return $days;
    }
}
