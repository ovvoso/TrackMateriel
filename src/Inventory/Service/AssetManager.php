<?php

declare(strict_types=1);

namespace App\Inventory\Service;

use App\Inventory\Entity\Asset;
use App\Inventory\Entity\Location;
use App\Inventory\Enum\AssetEventType;
use App\Inventory\Form\AssetInput;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Target;
use Symfony\Component\Workflow\Exception\TransitionException;
use Symfony\Component\Workflow\WorkflowInterface;

/**
 * Point d'entrée des opérations d'écriture sur l'inventaire. Chaque opération
 * laisse une trace dans le journal de l'équipement.
 */
final readonly class AssetManager
{
    /**
     * Libellés des champs suivis dans le journal.
     */
    private const array FIELD_LABELS = [
        'name' => 'désignation',
        'category' => 'catégorie',
        'brand' => 'marque',
        'model' => 'modèle',
        'serialNumber' => 'numéro de série',
        'condition' => 'état physique',
        'purchasedAt' => 'date d’achat',
        'purchasePrice' => 'prix d’achat',
        'warrantyEndsAt' => 'garantie',
        'supplier' => 'fournisseur',
        'notes' => 'notes',
    ];

    public function __construct(
        private EntityManagerInterface $entityManager,
        private InventoryCodeGenerator $codes,
        private AssetHistory $history,
        #[Target('asset_lifecycle')]
        private WorkflowInterface $lifecycle,
    ) {
    }

    /**
     * Ajoute un équipement à l'inventaire ; son code est attribué à ce moment.
     */
    public function create(AssetInput $input): Asset
    {
        \assert(null !== $input->name && null !== $input->category);

        $asset = new Asset($this->codes->next(), $input->name, $input->category);
        $this->fill($asset, $input);
        $this->entityManager->persist($asset);

        $this->history->record($asset, AssetEventType::Created, 'Équipement ajouté à l’inventaire');
        if (null !== $asset->getLocation()) {
            $this->history->record($asset, AssetEventType::Moved, 'Rangé : '.$asset->getLocation()->getPath());
        }

        $this->entityManager->flush();

        return $asset;
    }

    /**
     * Enregistre une fiche modifiée ; le journal distingue un déplacement
     * d'une mise à jour des caractéristiques.
     */
    public function update(Asset $asset, AssetInput $input): void
    {
        $before = AssetInput::fromAsset($asset);
        $changed = array_keys(array_diff_assoc(
            array_map(strval(...), $input->fingerprint()),
            array_map(strval(...), $before->fingerprint()),
        ));

        $this->fill($asset, $input);

        if ($before->location !== $input->location) {
            $this->history->record($asset, AssetEventType::Moved, \sprintf(
                'Déplacé : %s → %s',
                self::describe($before->location),
                self::describe($input->location),
            ));
        }

        if ([] !== $changed) {
            $labels = array_map(static fn (string $field) => self::FIELD_LABELS[$field], $changed);
            $this->history->record($asset, AssetEventType::Updated, 'Fiche modifiée : '.implode(', ', $labels));
        }

        $this->entityManager->flush();
    }

    /**
     * @throws TransitionException si la transition est inconnue ou pas permise
     *                             (état actuel ou droits de l'utilisateur)
     */
    public function apply(Asset $asset, string $transition): void
    {
        $this->lifecycle->apply($asset, $transition);
        $this->entityManager->flush();
    }

    private function fill(Asset $asset, AssetInput $input): void
    {
        \assert(null !== $input->name && null !== $input->category);

        $asset->setName($input->name);
        $asset->setCategory($input->category);
        $asset->setBrand($input->brand);
        $asset->setModel($input->model);
        $asset->setSerialNumber(AssetInput::normalizeSerial($input->serialNumber));
        $asset->setLocation($input->location);
        $asset->setCondition($input->condition);
        $asset->setPurchasedAt($input->purchasedAt);
        $asset->setPurchasePrice($input->purchasePrice);
        $asset->setWarrantyEndsAt($input->warrantyEndsAt);
        $asset->setSupplier($input->supplier);
        $asset->setNotes($input->notes);
    }

    private static function describe(?Location $location): string
    {
        return $location?->getPath() ?? 'sans emplacement';
    }
}
