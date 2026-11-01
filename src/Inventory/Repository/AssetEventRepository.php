<?php

declare(strict_types=1);

namespace App\Inventory\Repository;

use App\Inventory\Entity\Asset;
use App\Inventory\Entity\AssetEvent;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<AssetEvent>
 */
class AssetEventRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AssetEvent::class);
    }

    /**
     * @return list<AssetEvent>
     */
    public function findForAsset(Asset $asset): array
    {
        /** @var list<AssetEvent> $events */
        $events = $this->createQueryBuilder('e')
            ->addSelect('u')
            ->leftJoin('e.actor', 'u')
            ->where('e.asset = :asset')
            ->setParameter('asset', $asset)
            ->orderBy('e.occurredAt', 'DESC')
            ->addOrderBy('e.id', 'DESC')
            ->getQuery()->getResult();

        return $events;
    }

    /**
     * Activité récente sur l'ensemble du parc.
     *
     * @return list<AssetEvent>
     */
    public function findLatest(int $limit): array
    {
        /** @var list<AssetEvent> $events */
        $events = $this->createQueryBuilder('e')
            ->addSelect('a', 'u')
            ->join('e.asset', 'a')
            ->leftJoin('e.actor', 'u')
            ->orderBy('e.occurredAt', 'DESC')
            ->addOrderBy('e.id', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()->getResult();

        return $events;
    }
}
