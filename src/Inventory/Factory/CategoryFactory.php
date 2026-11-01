<?php

declare(strict_types=1);

namespace App\Inventory\Factory;

use App\Inventory\Entity\Category;
use Zenstruck\Foundry\Persistence\PersistentObjectFactory;

/**
 * @extends PersistentObjectFactory<Category>
 */
final class CategoryFactory extends PersistentObjectFactory
{
    public static function class(): string
    {
        return Category::class;
    }

    /**
     * @return array<string, mixed>
     */
    protected function defaults(): array
    {
        return [
            'name' => rtrim(self::faker()->unique()->sentence(2), '.'),
        ];
    }
}
