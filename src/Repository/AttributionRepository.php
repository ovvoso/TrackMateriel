<?php

namespace App\Repository;

use App\Entity\Attribution;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Attribution>
 */
class AttributionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Attribution::class);
    }

    //    /**
    //     * @return Attribution[] Returns an array of Attribution objects
    //     */
    //    public function findByExampleField($value): array
    //    {
    //        return $this->createQueryBuilder('a')
    //            ->andWhere('a.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->orderBy('a.id', 'ASC')
    //            ->setMaxResults(10)
    //            ->getQuery()
    //            ->getResult()
    //        ;
    //    }

    //    public function findOneBySomeField($value): ?Attribution
    //    {
    //        return $this->createQueryBuilder('a')
    //            ->andWhere('a.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->getQuery()
    //            ->getOneOrNullResult()
    //        ;
    //    }

    /**
     * Find attribution with all related entities loaded
     */
    public function findWithRelations(int $id): ?Attribution
    {
        return $this->createQueryBuilder('a')
            ->leftJoin('a.equipement', 'e')
            ->leftJoin('a.utilisateur', 'u')
            ->addSelect('e', 'u')
            ->where('a.id = :id')
            ->setParameter('id', $id)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Find all attributions with related entities
     */
    public function findAllWithRelations(): array
    {
        return $this->createQueryBuilder('a')
            ->leftJoin('a.equipement', 'e')
            ->leftJoin('a.utilisateur', 'u')
            ->addSelect('e', 'u')
            ->orderBy('a.dateAttribution', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find attributions for a specific user
     */
    public function findByUserWithRelations(User $user): array
    {
        return $this->createQueryBuilder('a')
            ->leftJoin('a.equipement', 'e')
            ->leftJoin('a.utilisateur', 'u')
            ->addSelect('e', 'u')
            ->where('a.utilisateur = :user')
            ->setParameter('user', $user)
            ->orderBy('a.dateAttribution', 'DESC')
            ->getQuery()
            ->getResult();
    }
}
