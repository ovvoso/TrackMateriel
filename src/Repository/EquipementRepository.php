<?php

namespace App\Repository;

use App\Entity\Equipement;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Equipement>
 */
class EquipementRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Equipement::class);
    }

    //    /**
    //     * @return Equipement[] Returns an array of Equipement objects
    //     */
    //    public function findByExampleField($value): array
    //    {
    //        return $this->createQueryBuilder('e')
    //            ->andWhere('e.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->orderBy('e.id', 'ASC')
    //            ->setMaxResults(10)
    //            ->getQuery()
    //            ->getResult()
    //        ;
    //    }

    //    public function findOneBySomeField($value): ?Equipement
    //    {
    //        return $this->createQueryBuilder('e')
    //            ->andWhere('e.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->getQuery()
    //            ->getOneOrNullResult()
    //        ;
    //    }

    public function findByFilters(?string $type = null, ?string $etat = null): array
    {
        $qb = $this->createQueryBuilder('e')
            ->orderBy('e.nom', 'ASC');

        if ($type) {
            $qb->andWhere('e.type = :type')
               ->setParameter('type', $type);
        }

        if ($etat) {
            $qb->andWhere('e.etat = :etat')
               ->setParameter('etat', $etat);
        }

        return $qb->getQuery()->getResult();
    }

    public function findByUserAndFilters(User $user, ?string $type = null, ?string $etat = null): array
    {
        $qb = $this->createQueryBuilder('e')
            ->innerJoin('e.attributions', 'a')
            ->where('a.utilisateur = :user')
            ->setParameter('user', $user)
            ->orderBy('e.nom', 'ASC');

        if ($type) {
            $qb->andWhere('e.type = :type')
               ->setParameter('type', $type);
        }

        if ($etat) {
            $qb->andWhere('e.etat = :etat')
               ->setParameter('etat', $etat);
        }

        return $qb->getQuery()->getResult();
    }

    public function getAvailableTypes(): array
    {
        $result = $this->createQueryBuilder('e')
            ->select('DISTINCT e.type')
            ->orderBy('e.type', 'ASC')
            ->getQuery()
            ->getScalarResult();

        return array_column($result, 'type');
    }

    public function getAvailableStates(): array
    {
        $result = $this->createQueryBuilder('e')
            ->select('DISTINCT e.etat')
            ->orderBy('e.etat', 'ASC')
            ->getQuery()
            ->getScalarResult();

        return array_column($result, 'etat');
    }
}
