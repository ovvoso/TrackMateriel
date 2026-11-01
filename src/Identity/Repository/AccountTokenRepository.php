<?php

declare(strict_types=1);

namespace App\Identity\Repository;

use App\Identity\Entity\AccountToken;
use App\Identity\Entity\User;
use App\Identity\Enum\TokenPurpose;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<AccountToken>
 */
class AccountTokenRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AccountToken::class);
    }

    /**
     * Invalide les liens encore ouverts d'un compte pour un usage donné :
     * seul le dernier lien envoyé reste valable.
     */
    public function revokeOpen(User $user, TokenPurpose $purpose, \DateTimeImmutable $now): void
    {
        $this->createQueryBuilder('t')
            ->update()
            ->set('t.usedAt', ':now')
            ->where('t.user = :user AND t.purpose = :purpose AND t.usedAt IS NULL')
            ->setParameter('now', $now)
            ->setParameter('user', $user)
            ->setParameter('purpose', $purpose)
            ->getQuery()->execute();
    }

    public function hasOpenInvitation(User $user, \DateTimeImmutable $now): bool
    {
        return null !== $this->createQueryBuilder('t')
            ->select('t.id')
            ->where('t.user = :user AND t.purpose = :purpose AND t.usedAt IS NULL AND t.expiresAt > :now')
            ->setParameter('user', $user)
            ->setParameter('purpose', TokenPurpose::Invitation)
            ->setParameter('now', $now)
            ->setMaxResults(1)
            ->getQuery()->getOneOrNullResult();
    }
}
