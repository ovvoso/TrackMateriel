<?php

declare(strict_types=1);

namespace App\Identity\Service;

/**
 * Règle de gestion des comptes non respectée ; le message s'adresse à l'utilisateur.
 */
final class AccountException extends \DomainException
{
}
