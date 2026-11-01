<?php

declare(strict_types=1);

namespace App\Lending\Service;

/**
 * Règle de prêt non respectée ; le message s'adresse à l'utilisateur.
 */
final class LendingException extends \DomainException
{
}
