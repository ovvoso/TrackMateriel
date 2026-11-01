<?php

declare(strict_types=1);

namespace App\Inventory\Repository;

use App\Inventory\Entity\Asset;
use App\Inventory\Enum\AssetStatus;
use App\Shared\Pagination\Page;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Bridge\Doctrine\Types\UuidType;

/**
 * @extends ServiceEntityRepository<Asset>
 */
class AssetRepository extends ServiceEntityRepository
{
    public const int PER_PAGE = 25;

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Asset::class);
    }

    public function findOneByCode(string $code): ?Asset
    {
        return $this->findOneBy(['code' => mb_strtoupper($code)]);
    }

    /**
     * @return Page<Asset>
     */
    public function search(AssetSearch $search): Page
    {
        /** @var Page<Asset> $page */
        $page = Page::fromQuery($this->searchQuery($search), $search->page, self::PER_PAGE);

        return $page;
    }

    /**
     * Tous les résultats d'une recherche, pour l'export.
     *
     * @return iterable<Asset>
     */
    public function searchAll(AssetSearch $search): iterable
    {
        /** @var iterable<Asset> $assets */
        $assets = $this->searchQuery($search)->getQuery()->toIterable();

        return $assets;
    }

    /**
     * @return array<string, int> nombre d'équipements par statut (valeur de l'enum)
     */
    public function countByStatus(): array
    {
        /** @var list<array{status: AssetStatus, total: int}> $rows */
        $rows = $this->createQueryBuilder('a')
            ->select('a.status AS status, COUNT(a.id) AS total')
            ->groupBy('a.status')
            ->getQuery()->getResult();

        $counts = array_fill_keys(array_map(static fn (AssetStatus $s) => $s->value, AssetStatus::cases()), 0);
        foreach ($rows as $row) {
            $counts[$row['status']->value] = (int) $row['total'];
        }

        return $counts;
    }

    /**
     * Répartition du parc actif (hors réformés) par catégorie, de la plus fournie à la moins fournie.
     *
     * @return list<array{name: string, total: int}>
     */
    public function countByCategory(int $limit = 6): array
    {
        /** @var list<array{name: string, total: int}> $rows */
        $rows = $this->createQueryBuilder('a')
            ->select('c.name AS name, COUNT(a.id) AS total')
            ->join('a.category', 'c')
            ->where('a.status != :retired')
            ->setParameter('retired', AssetStatus::Retired)
            ->groupBy('c.id, c.name')
            ->orderBy('total', 'DESC')
            ->addOrderBy('c.name', 'ASC')
            ->setMaxResults($limit)
            ->getQuery()->getResult();

        return array_map(static fn (array $row) => ['name' => $row['name'], 'total' => (int) $row['total']], $rows);
    }

    /**
     * Équipements actifs dont la garantie se termine dans la période donnée.
     *
     * @return list<Asset>
     */
    public function findWarrantiesEndingBetween(\DateTimeImmutable $from, \DateTimeImmutable $to, int $limit = 5): array
    {
        /** @var list<Asset> $assets */
        $assets = $this->createQueryBuilder('a')
            ->where('a.warrantyEndsAt BETWEEN :from AND :to')
            ->andWhere('a.status != :retired')
            ->setParameter('from', $from)
            ->setParameter('to', $to)
            ->setParameter('retired', AssetStatus::Retired)
            ->orderBy('a.warrantyEndsAt', 'ASC')
            ->setMaxResults($limit)
            ->getQuery()->getResult();

        return $assets;
    }

    private function searchQuery(AssetSearch $search): QueryBuilder
    {
        $qb = $this->createQueryBuilder('a')
            ->addSelect('c', 'l', 'lp', 'lpp')
            ->join('a.category', 'c')
            ->leftJoin('a.location', 'l')
            ->leftJoin('l.parent', 'lp')
            ->leftJoin('lp.parent', 'lpp');

        if (null !== $search->query) {
            $qb->andWhere('LOWER(a.code) LIKE :q OR LOWER(a.name) LIKE :q OR LOWER(a.serialNumber) LIKE :q OR LOWER(a.brand) LIKE :q OR LOWER(a.model) LIKE :q')
                ->setParameter('q', '%'.addcslashes(mb_strtolower($search->query), '%_').'%');
        }

        if (null !== $search->category) {
            $qb->andWhere('c.id = :category')->setParameter('category', $search->category, UuidType::NAME);
        }

        if (null !== $search->status) {
            $qb->andWhere('a.status = :status')->setParameter('status', $search->status);
        }

        // Un site ou un bâtiment inclut tout ce qui se trouve dans ses salles
        if (null !== $search->location) {
            $qb->andWhere('l.id = :location OR lp.id = :location OR lpp.id = :location')
                ->setParameter('location', $search->location, UuidType::NAME);
        }

        return match ($search->sort) {
            'name' => $qb->orderBy('a.name', 'ASC')->addOrderBy('a.code', 'ASC'),
            'updated' => $qb->orderBy('a.updatedAt', 'DESC'),
            default => $qb->orderBy('a.code', 'ASC'),
        };
    }
}
