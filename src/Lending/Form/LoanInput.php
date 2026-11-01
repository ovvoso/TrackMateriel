<?php

declare(strict_types=1);

namespace App\Lending\Form;

use App\Identity\Entity\User;
use App\Inventory\Entity\Asset;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

/**
 * Saisie d'un nouveau prêt.
 */
final class LoanInput
{
    public const string TO_ACCOUNT = 'account';
    public const string TO_PERSON = 'person';

    #[Assert\Choice(choices: [self::TO_ACCOUNT, self::TO_PERSON])]
    public string $borrowerType = self::TO_ACCOUNT;

    public ?User $account = null;

    #[Assert\Length(max: 160)]
    public ?string $personName = null;

    #[Assert\Length(max: 80)]
    public ?string $personGroup = null;

    /** @var list<Asset> */
    #[Assert\Count(min: 1, minMessage: 'Choisissez au moins un équipement à prêter.')]
    public array $assets = [];

    #[Assert\NotNull(message: 'Indiquez la date de retour prévue.')]
    #[Assert\GreaterThanOrEqual('today', message: 'La date de retour ne peut pas être passée.')]
    #[Assert\LessThanOrEqual('+1 year', message: 'Un prêt ne peut pas dépasser un an.')]
    public ?\DateTimeImmutable $dueAt = null;

    #[Assert\Length(max: 160)]
    public ?string $purpose = null;

    #[Assert\Length(max: 2000)]
    public ?string $notes = null;

    #[Assert\Callback]
    public function validateBorrower(ExecutionContextInterface $context): void
    {
        if (self::TO_ACCOUNT === $this->borrowerType && null === $this->account) {
            $context->buildViolation('Choisissez la personne qui emprunte.')->atPath('account')->addViolation();
        }

        if (self::TO_PERSON === $this->borrowerType && '' === trim((string) $this->personName)) {
            $context->buildViolation('Indiquez le nom de la personne qui emprunte.')->atPath('personName')->addViolation();
        }
    }
}
