<?php

declare(strict_types=1);

namespace App\Identity\Service;

use App\Identity\Entity\User;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * E-mails liés au compte. Les liens sont absolus : ils sont ouverts depuis
 * une messagerie, hors de l'application.
 */
final readonly class AccountMailer
{
    public function __construct(
        private MailerInterface $mailer,
        private UrlGeneratorInterface $urls,
    ) {
    }

    /**
     * @param array{id: string, secret: string} $link
     */
    public function sendInvitation(User $user, array $link, ?User $invitedBy): void
    {
        $this->mailer->send((new TemplatedEmail())
            ->to(new Address($user->getEmail(), $user->getFullName()))
            ->subject('Votre accès à TrackMatériel')
            ->htmlTemplate('email/invitation.html.twig')
            ->context([
                'user' => $user,
                'invited_by' => $invitedBy,
                'url' => $this->urls->generate('app_account_activate', $link, UrlGeneratorInterface::ABSOLUTE_URL),
            ]));
    }

    /**
     * @param array{id: string, secret: string} $link
     */
    public function sendPasswordReset(User $user, array $link): void
    {
        $this->mailer->send((new TemplatedEmail())
            ->to(new Address($user->getEmail(), $user->getFullName()))
            ->subject('Réinitialisation de votre mot de passe')
            ->htmlTemplate('email/password_reset.html.twig')
            ->context([
                'user' => $user,
                'url' => $this->urls->generate('app_password_reset', $link, UrlGeneratorInterface::ABSOLUTE_URL),
            ]));
    }
}
