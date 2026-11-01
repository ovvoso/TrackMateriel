<?php

declare(strict_types=1);

namespace App\Inventory\Enum;

/**
 * État physique constaté, indépendant du cycle de vie.
 */
enum AssetCondition: string
{
    case New = 'new';
    case Good = 'good';
    case Worn = 'worn';
    case Damaged = 'damaged';

    public function label(): string
    {
        return match ($this) {
            self::New => 'Neuf',
            self::Good => 'Bon état',
            self::Worn => 'Usé',
            self::Damaged => 'Endommagé',
        };
    }
}
