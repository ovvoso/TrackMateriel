<?php

declare(strict_types=1);

namespace App\Lending\Model;

use App\Identity\Entity\User;

/**
 * L'emprunteur d'un prêt : soit un compte de l'application (un enseignant),
 * soit une personne sans compte (un élève), décrite par son nom et sa classe.
 * Le nom est copié au moment du prêt pour que l'historique reste lisible.
 */
final readonly class Borrower
{
    private function __construct(
        public ?User $account,
        public string $name,
        public ?string $group,
    ) {
    }

    public static function account(User $user): self
    {
        return new self($user, $user->getFullName(), null);
    }

    public static function person(string $name, ?string $group): self
    {
        $group = null === $group ? null : trim($group);

        return new self(null, trim($name), '' === $group ? null : $group);
    }

    /**
     * Reconstitue l'emprunteur à partir des colonnes du prêt.
     */
    public static function restore(?User $account, string $name, ?string $group): self
    {
        return new self($account, $name, $group);
    }

    public function isAccount(User $user): bool
    {
        return null !== $this->account && $this->account->getId()->equals($user->getId());
    }

    public function describe(): string
    {
        return null === $this->group ? $this->name : $this->name.' · '.$this->group;
    }
}
