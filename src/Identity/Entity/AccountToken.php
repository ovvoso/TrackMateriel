<?php

declare(strict_types=1);

namespace App\Identity\Entity;

use App\Identity\Enum\TokenPurpose;
use App\Identity\Repository\AccountTokenRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

/**
 * Lien à usage unique envoyé par e-mail (invitation, mot de passe oublié).
 *
 * Le lien contient l'identifiant du jeton et un secret ; seule l'empreinte
 * SHA-256 du secret est stockée : une fuite de la base ne donne aucun lien valide.
 */
#[ORM\Entity(repositoryClass: AccountTokenRepository::class)]
#[ORM\Table(name: 'app_account_token')]
class AccountToken
{
    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME, unique: true)]
    private Uuid $id;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private User $user;

    #[ORM\Column(length: 20, enumType: TokenPurpose::class)]
    private TokenPurpose $purpose;

    #[ORM\Column(length: 64)]
    private string $secretHash;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $expiresAt;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $usedAt = null;

    public function __construct(User $user, TokenPurpose $purpose, string $secret, \DateTimeImmutable $now)
    {
        $this->id = Uuid::v7();
        $this->user = $user;
        $this->purpose = $purpose;
        $this->secretHash = self::hash($secret);
        $this->createdAt = $now;
        $this->expiresAt = $now->modify($purpose->lifetime());
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getUser(): User
    {
        return $this->user;
    }

    public function getPurpose(): TokenPurpose
    {
        return $this->purpose;
    }

    public function getExpiresAt(): \DateTimeImmutable
    {
        return $this->expiresAt;
    }

    /**
     * Comparaison en temps constant, pour ne rien révéler par la durée de réponse.
     */
    public function isUsableWith(string $secret, \DateTimeImmutable $now): bool
    {
        return null === $this->usedAt
            && $now < $this->expiresAt
            && hash_equals($this->secretHash, self::hash($secret));
    }

    public function markUsed(\DateTimeImmutable $at): void
    {
        $this->usedAt = $at;
    }

    public function isUsed(): bool
    {
        return null !== $this->usedAt;
    }

    private static function hash(string $secret): string
    {
        return hash('sha256', $secret);
    }
}
