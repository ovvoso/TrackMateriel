<?php

declare(strict_types=1);

namespace App\Inventory\Service;

use App\Identity\Entity\User;
use App\Inventory\Entity\Asset;
use App\Inventory\Entity\AssetEvent;
use App\Inventory\Enum\AssetEventType;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Clock\ClockInterface;
use Symfony\Bundle\SecurityBundle\Security;

/**
 * Inscrit les événements au journal d'un équipement, au nom de l'utilisateur connecté.
 * L'écriture est faite au prochain flush, dans la même transaction que la modification.
 */
final readonly class AssetHistory
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private Security $security,
        private ClockInterface $clock,
    ) {
    }

    public function record(Asset $asset, AssetEventType $type, string $summary): void
    {
        $actor = $this->security->getUser();
        $now = $this->clock->now();

        $asset->touch($now);
        $this->entityManager->persist(new AssetEvent($asset, $type, $summary, $actor instanceof User ? $actor : null, $now));
    }
}
