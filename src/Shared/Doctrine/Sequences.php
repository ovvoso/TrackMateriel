<?php

declare(strict_types=1);

namespace App\Shared\Doctrine;

use Doctrine\DBAL\Connection;

/**
 * Accès aux séquences PostgreSQL utilisées pour les numéros lisibles
 * (codes d'inventaire, références de prêt) : deux saisies simultanées ne
 * peuvent jamais obtenir le même numéro.
 */
final readonly class Sequences
{
    /** Séquences créées par migration ; à exclure du schéma ORM (doctrine.yaml) */
    public const string ASSET_CODE = 'inventory_asset_code_seq';
    public const string LOAN_REFERENCE = 'lending_loan_reference_seq';

    public function __construct(private Connection $connection)
    {
    }

    public function next(string $sequence): int
    {
        if (!\in_array($sequence, [self::ASSET_CODE, self::LOAN_REFERENCE], true)) {
            throw new \InvalidArgumentException(\sprintf('Séquence inconnue « %s ».', $sequence));
        }

        $value = $this->connection->fetchOne(\sprintf("SELECT nextval('%s')", $sequence));
        \assert(is_numeric($value));

        return (int) $value;
    }
}
