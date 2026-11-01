<?php

declare(strict_types=1);

namespace App\Inventory\Repository;

use App\Inventory\Entity\Asset;
use App\Inventory\Entity\Category;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Category>
 */
class CategoryRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Category::class);
    }

    /**
     * @return list<array{category: Category, total: int}>
     */
    public function findAllWithAssetCount(): array
    {
        /** @var list<array{0: Category, total: int}> $rows */
        $rows = $this->createQueryBuilder('c')
            ->select('c, COUNT(a.id) AS total')
            ->leftJoin(Asset::class, 'a', 'WITH', 'a.category = c')
            ->groupBy('c.id')
            ->orderBy('c.name', 'ASC')
            ->getQuery()->getResult();

        return array_map(static fn (array $row) => ['category' => $row[0], 'total' => (int) $row['total']], $rows);
    }

    public function isInUse(Category $category): bool
    {
        return null !== $this->getEntityManager()->getRepository(Asset::class)->findOneBy(['category' => $category]);
    }

    public function save(Category $category): void
    {
        $this->getEntityManager()->persist($category);
        $this->getEntityManager()->flush();
    }

    public function remove(Category $category): void
    {
        $this->getEntityManager()->remove($category);
        $this->getEntityManager()->flush();
    }
}
