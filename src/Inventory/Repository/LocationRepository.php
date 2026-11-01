<?php

declare(strict_types=1);

namespace App\Inventory\Repository;

use App\Inventory\Entity\Asset;
use App\Inventory\Entity\Location;
use App\Inventory\Enum\LocationKind;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Location>
 */
class LocationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Location::class);
    }

    /**
     * Les sites, avec bâtiments et salles chargés en une requête.
     *
     * @return list<Location>
     */
    public function findTree(): array
    {
        /** @var list<Location> $sites */
        $sites = $this->createQueryBuilder('s')
            ->addSelect('b', 'r')
            ->leftJoin('s.children', 'b')
            ->leftJoin('b.children', 'r')
            ->where('s.kind = :site')
            ->setParameter('site', LocationKind::Site)
            ->orderBy('s.name')->addOrderBy('b.name')->addOrderBy('r.name')
            ->getQuery()->getResult();

        return $sites;
    }

    /**
     * Tous les emplacements, triés par chemin complet, pour les listes de choix.
     *
     * @return list<Location>
     */
    public function findAllOrderedByPath(): array
    {
        $locations = [];
        foreach ($this->findTree() as $site) {
            $locations[] = $site;
            foreach ($site->getChildren() as $building) {
                $locations[] = $building;
                foreach ($building->getChildren() as $room) {
                    $locations[] = $room;
                }
            }
        }

        return $locations;
    }

    /**
     * Nombre d'équipements rangés directement à chaque emplacement, indexé par identifiant.
     *
     * @return array<string, int>
     */
    public function countAssetsPerLocation(): array
    {
        /** @var list<array{id: string, total: int}> $rows */
        $rows = $this->getEntityManager()->createQueryBuilder()
            ->select('IDENTITY(a.location) AS id, COUNT(a.id) AS total')
            ->from(Asset::class, 'a')
            ->where('a.location IS NOT NULL')
            ->groupBy('a.location')
            ->getQuery()->getResult();

        return array_column(array_map(static fn (array $r) => ['id' => (string) $r['id'], 'total' => (int) $r['total']], $rows), 'total', 'id');
    }

    public function isInUse(Location $location): bool
    {
        if (!$location->getChildren()->isEmpty()) {
            return true;
        }

        return null !== $this->getEntityManager()->getRepository(Asset::class)->findOneBy(['location' => $location]);
    }

    public function save(Location $location): void
    {
        $this->getEntityManager()->persist($location);
        $this->getEntityManager()->flush();
    }

    public function remove(Location $location): void
    {
        $this->getEntityManager()->remove($location);
        $this->getEntityManager()->flush();
    }
}
