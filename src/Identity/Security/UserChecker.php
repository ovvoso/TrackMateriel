<?php

declare(strict_types=1);

namespace App\Identity\Security;

use App\Identity\Entity\User;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAccountStatusException;
use Symfony\Component\Security\Core\User\UserCheckerInterface;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * Un compte désactivé, ou pas encore activé depuis son invitation, ne peut pas
 * se connecter ; une session déjà ouverte est coupée au rechargement suivant.
 */
final class UserChecker implements UserCheckerInterface
{
    public function checkPreAuth(UserInterface $user): void
    {
        if (!$user instanceof User) {
            return;
        }

        if (!$user->isActive()) {
            throw new CustomUserMessageAccountStatusException('Ce compte est désactivé. Contactez votre administrateur.');
        }

        if (!$user->isActivated()) {
            throw new CustomUserMessageAccountStatusException('Ce compte n’est pas encore activé : utilisez le lien reçu par e-mail pour choisir votre mot de passe.');
        }
    }

    public function checkPostAuth(UserInterface $user, ?TokenInterface $token = null): void
    {
    }
}
