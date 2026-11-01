<?php

declare(strict_types=1);

namespace App\Lending\Enum;

/**
 * Situation d'un prêt, déduite de ses dates : elle n'est pas stockée.
 */
enum LoanState: string
{
    case Ongoing = 'en-cours';
    case Overdue = 'en-retard';
    case Returned = 'rendus';

    public function label(): string
    {
        return match ($this) {
            self::Ongoing => 'En cours',
            self::Overdue => 'En retard',
            self::Returned => 'Rendu',
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::Ongoing => 'accent',
            self::Overdue => 'danger',
            self::Returned => 'neutral',
        };
    }
}
