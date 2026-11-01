<?php

declare(strict_types=1);

namespace App\Inventory\Factory;

use App\Inventory\Entity\Asset;
use App\Inventory\Enum\AssetStatus;
use App\Inventory\Service\InventoryCodeGenerator;
use Zenstruck\Foundry\Persistence\PersistentObjectFactory;

use function Zenstruck\Foundry\lazy;

/**
 * Les codes viennent de la vraie séquence, pour ne jamais entrer en conflit
 * avec les équipements saisis ensuite dans l'application.
 *
 * @extends PersistentObjectFactory<Asset>
 */
final class AssetFactory extends PersistentObjectFactory
{
    public function __construct(private readonly InventoryCodeGenerator $codes)
    {
        parent::__construct();
    }

    public static function class(): string
    {
        return Asset::class;
    }

    /**
     * @return array<string, mixed>
     */
    protected function defaults(): array
    {
        return [
            'code' => lazy(fn () => $this->codes->next()),
            'name' => rtrim(self::faker()->sentence(3), '.'),
            'category' => CategoryFactory::new(),
        ];
    }

    public function withStatus(AssetStatus $status): static
    {
        return $this->afterInstantiate(static fn (Asset $asset) => $asset->setStatus($status));
    }
}
