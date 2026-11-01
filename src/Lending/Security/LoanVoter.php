<?php

declare(strict_types=1);

namespace App\Lending\Security;

use App\Identity\Entity\User;
use App\Lending\Entity\Loan;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\AccessDecisionManagerInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * Le gestionnaire de parc sort et reçoit le matériel ; un emprunteur qui a un
 * compte consulte ses propres prêts, et seulement les siens.
 *
 * @extends Voter<string, Loan|null>
 */
final class LoanVoter extends Voter
{
    /** Créer des prêts et voir tous les prêts */
    public const string MANAGE = 'LOAN_MANAGE';

    /** Consulter un prêt */
    public const string VIEW = 'LOAN_VIEW';

    /** Enregistrer un retour ou prolonger : prêt encore ouvert */
    public const string HANDLE = 'LOAN_HANDLE';

    public function __construct(private readonly AccessDecisionManagerInterface $decisions)
    {
    }

    protected function supports(string $attribute, mixed $subject): bool
    {
        return match ($attribute) {
            self::MANAGE => null === $subject,
            self::VIEW, self::HANDLE => $subject instanceof Loan,
            default => false,
        };
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        $isManager = $this->decisions->decide($token, ['ROLE_GESTIONNAIRE']);

        if (self::VIEW === $attribute && $subject instanceof Loan) {
            $user = $token->getUser();

            return $isManager || ($user instanceof User && $subject->getBorrower()->isAccount($user));
        }

        if (!$isManager) {
            $vote?->addReason('Réservé aux gestionnaires de parc.');

            return false;
        }

        if (self::HANDLE === $attribute && $subject instanceof Loan && $subject->isClosed()) {
            $vote?->addReason('Ce prêt est clos.');

            return false;
        }

        return true;
    }
}
