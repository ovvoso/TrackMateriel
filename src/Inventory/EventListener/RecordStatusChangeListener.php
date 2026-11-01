<?php

declare(strict_types=1);

namespace App\Inventory\EventListener;

use App\Inventory\Entity\Asset;
use App\Inventory\Enum\AssetEventType;
use App\Inventory\Enum\AssetStatus;
use App\Inventory\Service\AssetHistory;
use Symfony\Component\DependencyInjection\Attribute\Target;
use Symfony\Component\Workflow\Attribute\AsCompletedListener;
use Symfony\Component\Workflow\Event\CompletedEvent;
use Symfony\Component\Workflow\WorkflowInterface;

/**
 * Chaque changement d'état passé par le Workflow est inscrit au journal.
 */
#[AsCompletedListener(workflow: 'asset_lifecycle')]
final readonly class RecordStatusChangeListener
{
    public function __construct(
        private AssetHistory $history,
        #[Target('asset_lifecycle')]
        private WorkflowInterface $lifecycle,
    ) {
    }

    public function __invoke(CompletedEvent $event): void
    {
        $asset = $event->getSubject();
        \assert($asset instanceof Asset);

        $transition = $event->getTransition();
        \assert(null !== $transition);
        $from = $transition->getFroms()[0];
        $label = $this->lifecycle->getMetadataStore()->getTransitionMetadata($transition)['label'] ?? $transition->getName();
        \assert(\is_string($from) && \is_string($label));

        $this->history->record($asset, AssetEventType::StatusChanged, \sprintf(
            '%s : %s → %s',
            $label,
            AssetStatus::from($from)->label(),
            $asset->getStatus()->label(),
        ));
    }
}
