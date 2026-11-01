<?php

declare(strict_types=1);

namespace App\Inventory\Factory;

use App\Inventory\Entity\Location;
use App\Inventory\Enum\LocationKind;
use Zenstruck\Foundry\Persistence\PersistentObjectFactory;

/**
 * @extends PersistentObjectFactory<Location>
 */
final class LocationFactory extends PersistentObjectFactory
{
    public static function class(): string
    {
        return Location::class;
    }

    /**
     * @return array<string, mixed>
     */
    protected function defaults(): array
    {
        return [
            'name' => 'Site '.self::faker()->unique()->city(),
            'kind' => LocationKind::Site,
            'parent' => null,
        ];
    }

    public function building(Location $site, string $name): static
    {
        return $this->with(['name' => $name, 'kind' => LocationKind::Building, 'parent' => $site]);
    }

    public function room(Location $building, string $name): static
    {
        return $this->with(['name' => $name, 'kind' => LocationKind::Room, 'parent' => $building]);
    }
}
