<?php

declare(strict_types=1);

namespace App\Inventory\Service;

use Doctrine\DBAL\Connection;
use Psr\Clock\ClockInterface;

/**
 * Codes d'inventaire au format INV-2026-00417. Le numéro vient d'une séquence
 * PostgreSQL : deux saisies simultanées ne peuvent pas obtenir le même code.
 */
final readonly class InventoryCodeGenerator
{
    public const string SEQUENCE = 'inventory_asset_code_seq';

    public function __construct(
        private Connection $connection,
        private ClockInterface $clock,
    ) {
    }

    public function next(): string
    {
        $number = $this->connection->fetchOne(\sprintf("SELECT nextval('%s')", self::SEQUENCE));
        \assert(is_numeric($number));

        return \sprintf('INV-%s-%05d', $this->clock->now()->format('Y'), (int) $number);
    }
}
