<?php

declare(strict_types=1);

namespace App\Lending\Service;

use App\Shared\Doctrine\Sequences;
use Psr\Clock\ClockInterface;

/**
 * Références de prêt au format PRT-2026-00042, à reporter sur une fiche papier si besoin.
 */
final readonly class LoanReferenceGenerator
{
    public function __construct(
        private Sequences $sequences,
        private ClockInterface $clock,
    ) {
    }

    public function next(): string
    {
        return \sprintf('PRT-%s-%05d', $this->clock->now()->format('Y'), $this->sequences->next(Sequences::LOAN_REFERENCE));
    }
}
