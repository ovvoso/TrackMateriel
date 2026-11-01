<?php

declare(strict_types=1);

namespace App\Inventory\Enum;

/**
 * Étapes du cycle de vie d'un équipement. Les passages de l'une à l'autre
 * sont définis dans config/packages/workflow.yaml.
 */
enum AssetStatus: string
{
    case Available = 'available';
    case InService = 'in_service';
    case OnLoan = 'on_loan';
    case InMaintenance = 'in_maintenance';
    case OutOfOrder = 'out_of_order';
    case Retired = 'retired';

    public function label(): string
    {
        return match ($this) {
            self::Available => 'Disponible',
            self::InService => 'En service',
            self::OnLoan => 'Prêté',
            self::InMaintenance => 'En maintenance',
            self::OutOfOrder => 'Hors service',
            self::Retired => 'Réformé',
        };
    }

    /**
     * Ton de la pastille d'état dans le design system.
     */
    public function tone(): string
    {
        return match ($this) {
            self::Available => 'success',
            self::InService => 'info',
            self::OnLoan => 'accent',
            self::InMaintenance => 'warning',
            self::OutOfOrder => 'danger',
            self::Retired => 'neutral',
        };
    }
}
