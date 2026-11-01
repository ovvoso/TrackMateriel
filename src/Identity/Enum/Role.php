<?php

declare(strict_types=1);

namespace App\Identity\Enum;

/**
 * Profil d'un compte. Chaque compte a un seul profil ; la hiérarchie
 * (security.yaml) donne aux profils supérieurs les droits des profils inférieurs.
 */
enum Role: string
{
    case Admin = 'ROLE_ADMIN';
    case Gestionnaire = 'ROLE_GESTIONNAIRE';
    case Technicien = 'ROLE_TECHNICIEN';
    case Usager = 'ROLE_USAGER';

    public function label(): string
    {
        return match ($this) {
            self::Admin => 'Administrateur',
            self::Gestionnaire => 'Gestionnaire de parc',
            self::Technicien => 'Technicien',
            self::Usager => 'Usager',
        };
    }
}
