<?php

declare(strict_types=1);

namespace App\Lending\Repository;

use App\Identity\Entity\User;
use App\Lending\Enum\LoanState;
use Symfony\Component\HttpFoundation\Request;

/**
 * Critères de la liste des prêts. Sans précision d'état, la liste montre les
 * prêts ouverts (en cours et en retard).
 */
final readonly class LoanSearch
{
    public function __construct(
        public ?LoanState $state = null,
        public ?string $query = null,
        public ?User $borrower = null,
        public int $page = 1,
    ) {
    }

    public static function fromRequest(Request $request, ?User $restrictTo = null): self
    {
        $query = trim($request->query->getString('q'));

        return new self(
            state: LoanState::tryFrom($request->query->getString('etat')),
            query: '' === $query ? null : mb_substr($query, 0, 100),
            borrower: $restrictTo,
            page: max(1, $request->query->getInt('page', 1)),
        );
    }
}
