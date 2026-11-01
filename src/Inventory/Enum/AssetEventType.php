<?php

declare(strict_types=1);

namespace App\Inventory\Enum;

enum AssetEventType: string
{
    case Created = 'created';
    case Updated = 'updated';
    case Moved = 'moved';
    case StatusChanged = 'status_changed';

    public function icon(): string
    {
        return match ($this) {
            self::Created => 'lucide:plus',
            self::Updated => 'lucide:pencil',
            self::Moved => 'lucide:map-pin',
            self::StatusChanged => 'lucide:refresh-cw',
        };
    }
}
