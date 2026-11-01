<?php

declare(strict_types=1);

namespace App\Inventory\Enum;

/**
 * Niveaux d'emplacement : un site contient des bâtiments, un bâtiment des salles.
 */
enum LocationKind: string
{
    case Site = 'site';
    case Building = 'building';
    case Room = 'room';

    public function label(): string
    {
        return match ($this) {
            self::Site => 'Site',
            self::Building => 'Bâtiment',
            self::Room => 'Salle',
        };
    }

    public function parentKind(): ?self
    {
        return match ($this) {
            self::Site => null,
            self::Building => self::Site,
            self::Room => self::Building,
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Site => 'lucide:school',
            self::Building => 'lucide:building-2',
            self::Room => 'lucide:door-open',
        };
    }
}
