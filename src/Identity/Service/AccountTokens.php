<?php

declare(strict_types=1);

namespace App\Identity\Service;

use App\Identity\Entity\AccountToken;
use App\Identity\Entity\User;
use App\Identity\Enum\TokenPurpose;
use App\Identity\Repository\AccountTokenRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Clock\ClockInterface;
use Symfony\Component\Uid\Uuid;

/**
 * Émission et vérification des liens à usage unique. Émettre un lien
 * invalide les précédents du même usage pour ce compte.
 */
final readonly class AccountTokens
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private AccountTokenRepository $tokens,
        private ClockInterface $clock,
    ) {
    }

    /**
     * @return array{id: string, secret: string} les deux parties du lien ; le secret n'est stocké nulle part
     */
    public function issue(User $user, TokenPurpose $purpose): array
    {
        $now = $this->clock->now();
        $this->tokens->revokeOpen($user, $purpose, $now);

        $secret = bin2hex(random_bytes(24));
        $token = new AccountToken($user, $purpose, $secret, $now);
        $this->entityManager->persist($token);

        return ['id' => $token->getId()->toRfc4122(), 'secret' => $secret];
    }

    /**
     * Le jeton correspondant au lien, s'il est encore utilisable.
     */
    public function find(TokenPurpose $purpose, string $id, string $secret): ?AccountToken
    {
        if (!Uuid::isValid($id)) {
            return null;
        }

        $token = $this->tokens->find($id);

        return null !== $token && $token->getPurpose() === $purpose && $token->isUsableWith($secret, $this->clock->now()) ? $token : null;
    }
}
