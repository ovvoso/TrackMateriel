<?php

declare(strict_types=1);

namespace App\Identity\Service;

use App\Identity\Entity\AccountToken;
use App\Identity\Entity\User;
use App\Identity\Enum\Role;
use App\Identity\Enum\TokenPurpose;
use App\Identity\Form\InviteInput;
use App\Identity\Repository\AccountTokenRepository;
use App\Identity\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Clock\ClockInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Cycle de vie des comptes : invitation, activation, mot de passe, désactivation.
 * Les règles qui évitent de verrouiller l'établissement hors de son application
 * (dernier administrateur, auto-désactivation) sont appliquées ici.
 */
final readonly class AccountManager
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private UserRepository $users,
        private AccountTokenRepository $tokenRepository,
        private AccountTokens $tokens,
        private AccountMailer $mailer,
        private UserPasswordHasherInterface $hasher,
        private ClockInterface $clock,
    ) {
    }

    public function invite(InviteInput $input, User $invitedBy): User
    {
        \assert(null !== $input->email && null !== $input->firstName && null !== $input->lastName);

        $user = new User($input->email, trim($input->firstName), trim($input->lastName), $input->role);
        $this->entityManager->persist($user);
        $this->sendInvitation($user, $invitedBy);

        return $user;
    }

    public function sendInvitation(User $user, ?User $invitedBy): void
    {
        if ($user->isActivated()) {
            throw new AccountException('Ce compte est déjà activé.');
        }

        $link = $this->tokens->issue($user, TokenPurpose::Invitation);
        $this->entityManager->flush();
        $this->mailer->sendInvitation($user, $link, $invitedBy);
    }

    public function activate(AccountToken $token, string $plainPassword): User
    {
        $user = $token->getUser();
        $now = $this->clock->now();

        $user->activate($this->hasher->hashPassword($user, $plainPassword), $now);
        $token->markUsed($now);
        $this->entityManager->flush();

        return $user;
    }

    /**
     * Envoie un lien de réinitialisation si l'adresse correspond à un compte actif.
     * Le résultat n'est jamais révélé à l'appelant : on ne doit pas pouvoir
     * découvrir quelles adresses ont un compte.
     */
    public function requestPasswordReset(string $email): void
    {
        $user = $this->users->findOneByEmail($email);
        if (null === $user || !$user->isActive()) {
            return;
        }

        // Une invitation non acceptée vaut lien d'activation
        if (!$user->isActivated()) {
            $this->sendInvitation($user, null);

            return;
        }

        $link = $this->tokens->issue($user, TokenPurpose::PasswordReset);
        $this->entityManager->flush();
        $this->mailer->sendPasswordReset($user, $link);
    }

    public function resetPassword(AccountToken $token, string $plainPassword): void
    {
        $user = $token->getUser();
        $now = $this->clock->now();

        $user->setPassword($this->hasher->hashPassword($user, $plainPassword));
        $token->markUsed($now);
        $this->tokenRepository->revokeOpen($user, TokenPurpose::PasswordReset, $now);
        $this->entityManager->flush();
    }

    public function changePassword(User $user, string $plainPassword): void
    {
        $user->setPassword($this->hasher->hashPassword($user, $plainPassword));
        $this->entityManager->flush();
    }

    public function update(User $user, string $firstName, string $lastName, Role $role, User $actor): void
    {
        if ($user->getRole() !== $role) {
            $this->assertCanLoseAdminRights($user, $actor, 'changer votre propre profil');
        }

        $user->rename(trim($firstName), trim($lastName));
        $user->changeRole($role);
        $this->entityManager->flush();
    }

    public function deactivate(User $user, User $actor): void
    {
        $this->assertCanLoseAdminRights($user, $actor, 'désactiver votre propre compte');

        $user->deactivate();
        $this->tokenRepository->revokeOpen($user, TokenPurpose::Invitation, $this->clock->now());
        $this->tokenRepository->revokeOpen($user, TokenPurpose::PasswordReset, $this->clock->now());
        $this->entityManager->flush();
    }

    public function reactivate(User $user): void
    {
        $user->reactivate();
        $this->entityManager->flush();
    }

    private function assertCanLoseAdminRights(User $user, User $actor, string $selfAction): void
    {
        if ($user->getId()->equals($actor->getId())) {
            throw new AccountException(\sprintf('Vous ne pouvez pas %s : demandez-le à un autre administrateur.', $selfAction));
        }

        if (Role::Admin === $user->getRole() && $user->isActive() && $this->users->countActiveAdmins() <= 1) {
            throw new AccountException('C’est le dernier administrateur actif : nommez-en un autre avant.');
        }
    }
}
