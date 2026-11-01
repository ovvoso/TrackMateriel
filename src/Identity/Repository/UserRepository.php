<?php

declare(strict_types=1);

namespace App\Identity\Repository;

use App\Identity\Entity\User;
use App\Identity\Enum\Role;
use App\Shared\Pagination\Page;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Bridge\Doctrine\Security\User\UserLoaderInterface;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\PasswordUpgraderInterface;

/**
 * @extends ServiceEntityRepository<User>
 */
class UserRepository extends ServiceEntityRepository implements PasswordUpgraderInterface, UserLoaderInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, User::class);
    }

    public function findOneByEmail(string $email): ?User
    {
        return $this->findOneBy(['email' => mb_strtolower(trim($email))]);
    }

    /**
     * Connexion insensible à la casse : les adresses sont stockées en minuscules.
     */
    public function loadUserByIdentifier(string $identifier): ?User
    {
        return $this->findOneByEmail($identifier);
    }

    /**
     * @return list<User>
     */
    public function findActiveOrderedByName(): array
    {
        return $this->findBy(['active' => true], ['lastName' => 'ASC', 'firstName' => 'ASC']);
    }

    public function countActiveAdmins(): int
    {
        return $this->count(['role' => Role::Admin, 'active' => true]);
    }

    /**
     * @return Page<User>
     */
    public function search(?string $query, ?Role $role, ?string $status, int $page): Page
    {
        $qb = $this->createQueryBuilder('u')
            ->orderBy('u.lastName', 'ASC')
            ->addOrderBy('u.firstName', 'ASC');

        if (null !== $query) {
            $qb->andWhere('LOWER(u.email) LIKE :q OR LOWER(u.firstName) LIKE :q OR LOWER(u.lastName) LIKE :q')
                ->setParameter('q', '%'.addcslashes(mb_strtolower($query), '%_').'%');
        }

        if (null !== $role) {
            $qb->andWhere('u.role = :role')->setParameter('role', $role);
        }

        match ($status) {
            'actifs' => $qb->andWhere('u.active = true AND u.activatedAt IS NOT NULL'),
            'invites' => $qb->andWhere('u.active = true AND u.activatedAt IS NULL'),
            'desactives' => $qb->andWhere('u.active = false'),
            default => null,
        };

        /** @var Page<User> $result */
        $result = Page::fromQuery($qb, $page, 25);

        return $result;
    }

    public function save(User $user): void
    {
        $this->getEntityManager()->persist($user);
        $this->getEntityManager()->flush();
    }

    /**
     * Rehash transparent quand l'algorithme recommandé évolue.
     */
    public function upgradePassword(PasswordAuthenticatedUserInterface $user, string $newHashedPassword): void
    {
        if (!$user instanceof User) {
            throw new UnsupportedUserException(\sprintf('Instances of "%s" are not supported.', $user::class));
        }

        $user->setPassword($newHashedPassword);
        $this->getEntityManager()->flush();
    }
}
