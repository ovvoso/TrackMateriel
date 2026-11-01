<?php

declare(strict_types=1);

namespace App\Inventory\Service;

use App\Shared\Doctrine\Sequences;
use Psr\Clock\ClockInterface;

/**
 * Codes d'inventaire au format INV-2026-00417.
 */
final readonly class InventoryCodeGenerator
{
    public function __construct(
        private Sequences $sequences,
        private ClockInterface $clock,
    ) {
    }

    public function next(): string
    {
        return \sprintf('INV-%s-%05d', $this->clock->now()->format('Y'), $this->sequences->next(Sequences::ASSET_CODE));
    }
}
