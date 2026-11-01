<?php

declare(strict_types=1);

namespace App\Identity\Enum;

enum TokenPurpose: string
{
    case Invitation = 'invitation';
    case PasswordReset = 'password_reset';

    /**
     * Durée de validité d'un lien.
     */
    public function lifetime(): string
    {
        return match ($this) {
            self::Invitation => '+7 days',
            self::PasswordReset => '+1 hour',
        };
    }
}
