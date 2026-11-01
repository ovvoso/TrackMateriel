<?php

declare(strict_types=1);

namespace App\Inventory\Security;

use App\Inventory\Entity\Asset;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\AccessDecisionManagerInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * Droits sur l'inventaire. Tout compte connecté consulte le parc ; seul le
 * gestionnaire le modifie, et un équipement réformé n'est plus modifiable.
 *
 * @extends Voter<string, Asset|null>
 */
final class InventoryVoter extends Voter
{
    /** Ajouter des équipements, gérer emplacements et catégories */
    public const string MANAGE = 'INVENTORY_MANAGE';

    /** Modifier la fiche d'un équipement */
    public const string EDIT = 'ASSET_EDIT';

    /** Voir les informations d'achat (prix, fournisseur) */
    public const string VIEW_PURCHASE = 'ASSET_VIEW_PURCHASE';

    public function __construct(private readonly AccessDecisionManagerInterface $decisions)
    {
    }

    protected function supports(string $attribute, mixed $subject): bool
    {
        return match ($attribute) {
            self::MANAGE, self::VIEW_PURCHASE => null === $subject || $subject instanceof Asset,
            self::EDIT => $subject instanceof Asset,
            default => false,
        };
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        if (!$this->decisions->decide($token, ['ROLE_GESTIONNAIRE'])) {
            $vote?->addReason('Réservé aux gestionnaires de parc.');

            return false;
        }

        if (self::EDIT === $attribute && $subject instanceof Asset && $subject->isRetired()) {
            $vote?->addReason('Un équipement réformé ne peut plus être modifié.');

            return false;
        }

        return true;
    }
}
