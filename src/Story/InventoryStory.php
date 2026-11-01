<?php

declare(strict_types=1);

namespace App\Story;

use App\Identity\Entity\User;
use App\Identity\Factory\UserFactory;
use App\Inventory\Entity\Asset;
use App\Inventory\Entity\AssetEvent;
use App\Inventory\Entity\Category;
use App\Inventory\Entity\Location;
use App\Inventory\Enum\AssetCondition;
use App\Inventory\Enum\AssetEventType;
use App\Inventory\Enum\AssetStatus;
use App\Inventory\Factory\AssetFactory;
use App\Inventory\Factory\CategoryFactory;
use App\Inventory\Factory\LocationFactory;
use Faker\Generator;
use Zenstruck\Foundry\Story;

use function Zenstruck\Foundry\faker;
use function Zenstruck\Foundry\Persistence\save;

/**
 * Parc de démonstration d'un lycée : emplacements, catégories et une centaine
 * d'équipements avec un historique plausible.
 */
final class InventoryStory extends Story
{
    /**
     * Bâtiments et salles du campus principal.
     */
    private const array CAMPUS = [
        'Bâtiment A · Administration' => ['Secrétariat', 'Bureau du proviseur', 'Salle des professeurs', 'Réserve informatique'],
        'Bâtiment B · Sciences' => ['Laboratoire de physique-chimie', 'Laboratoire de SVT', 'Salle B12', 'Salle B14'],
        'Bâtiment C · Numérique' => ['Salle informatique 1', 'Salle informatique 2', 'Fab lab'],
        'CDI' => ['Espace lecture', 'Salle de travail'],
    ];

    /**
     * Catalogue : catégorie => [désignation, marque, modèle, quantité, prix (XOF), salle d'affectation].
     */
    private const array CATALOGUE = [
        'Ordinateur portable' => [
            ['Ordinateur portable élève', 'Dell', 'Latitude 3440', 10, 465000, 'Salle informatique 1'],
            ['Ordinateur portable élève', 'HP', 'ProBook 450 G10', 6, 495000, 'Salle informatique 2'],
            ['Ordinateur portable enseignant', 'Lenovo', 'ThinkPad E14 Gen 5', 4, 520000, 'Salle des professeurs'],
        ],
        'Ordinateur fixe' => [
            ['Poste de travail', 'HP', 'ProDesk 400 G9', 8, 385000, 'Salle informatique 2'],
            ['Poste administratif', 'Dell', 'OptiPlex 7010', 3, 410000, 'Secrétariat'],
        ],
        'Vidéoprojecteur' => [
            ['Vidéoprojecteur laser', 'Epson', 'EB-L210W', 4, 640000, 'Salle B12'],
            ['Vidéoprojecteur portable', 'BenQ', 'MW560', 3, 395000, 'Réserve informatique'],
        ],
        'Écran interactif' => [
            ['Écran interactif 75 pouces', 'Speechi', 'SPE-75', 2, 2450000, 'Salle B14'],
        ],
        'Tablette' => [
            ['Tablette de lecture', 'Apple', 'iPad 10e génération', 8, 290000, 'Espace lecture'],
            ['Tablette élève', 'Samsung', 'Galaxy Tab A9+', 6, 155000, 'Salle de travail'],
        ],
        'Imprimante' => [
            ['Imprimante laser réseau', 'HP', 'LaserJet Pro M404dn', 3, 235000, 'Secrétariat'],
            ['Imprimante laser', 'Brother', 'HL-L2350DW', 2, 98000, 'Salle des professeurs'],
        ],
        'Microscope' => [
            ['Microscope optique', 'Bresser', 'Biolux NV', 6, 120000, 'Laboratoire de SVT'],
        ],
        'Kit robotique' => [
            ['Kit robotique', 'LEGO Education', 'SPIKE Prime', 6, 265000, 'Fab lab'],
            ['Kit électronique', 'Arduino', 'Starter Kit', 8, 62000, 'Fab lab'],
        ],
        'Appareil photo' => [
            ['Appareil photo reflex', 'Canon', 'EOS 2000D', 2, 340000, 'Fab lab'],
        ],
        'Matériel audio' => [
            ['Enceinte portable', 'JBL', 'Charge 5', 3, 115000, 'Réserve informatique'],
            ['Micro sans fil', 'Shure', 'BLX24/PG58', 2, 285000, 'Réserve informatique'],
        ],
        'Réseau' => [
            ['Point d’accès Wi-Fi', 'Ubiquiti', 'U6 Lite', 4, 85000, 'Réserve informatique'],
        ],
    ];

    private const array SUPPLIERS = ['Teranga Numérique', 'Cap-Vert Informatique', 'Sahel Équipements scolaires', 'Dakar Bureautique'];

    /** @var array<string, Location> */
    private array $rooms = [];

    private Generator $faker;

    public function build(): void
    {
        $this->faker = faker();
        $this->faker->seed(2026);

        $manager = UserFactory::find(['email' => AppStory::MANAGER_EMAIL]);
        $technician = UserFactory::find(['email' => AppStory::TECHNICIAN_EMAIL]);

        $this->buildLocations();

        foreach (self::CATALOGUE as $categoryName => $lines) {
            $category = CategoryFactory::createOne(['name' => $categoryName]);
            foreach ($lines as [$name, $brand, $model, $quantity, $price, $room]) {
                for ($i = 0; $i < $quantity; ++$i) {
                    $this->createAsset($category, $name, $brand, $model, $price, $this->rooms[$room], $manager, $technician);
                }
            }
        }
    }

    private function buildLocations(): void
    {
        $campus = LocationFactory::createOne(['name' => 'Campus principal']);
        foreach (self::CAMPUS as $buildingName => $rooms) {
            $building = LocationFactory::new()->building($campus, $buildingName)->create();
            foreach ($rooms as $roomName) {
                $this->rooms[$roomName] = LocationFactory::new()->room($building, $roomName)->create();
            }
        }

        $annex = LocationFactory::createOne(['name' => 'Annexe sportive']);
        $gym = LocationFactory::new()->building($annex, 'Gymnase')->create();
        LocationFactory::new()->room($gym, 'Local matériel')->create();
    }

    private function createAsset(Category $category, string $name, string $brand, string $model, int $price, Location $room, User $manager, User $technician): void
    {
        $purchasedAt = \DateTimeImmutable::createFromMutable($this->faker->dateTimeBetween('-5 years', '-2 months'))->setTime(0, 0);
        $warrantyYears = $this->faker->numberBetween(1, 3);
        $status = $this->pickStatus($purchasedAt);

        $asset = AssetFactory::new()->withStatus($status)->create([
            'name' => $name,
            'category' => $category,
        ]);
        $asset->setBrand($brand);
        $asset->setModel($model);
        $asset->setSerialNumber(strtoupper($this->faker->bothify('??##-####-####')));
        $asset->setLocation(AssetStatus::Retired === $status ? null : $room);
        $asset->setCondition($this->pickCondition($purchasedAt, $status));
        $asset->setPurchasedAt($purchasedAt);
        $asset->setPurchasePrice((string) $price);
        $asset->setWarrantyEndsAt($purchasedAt->modify(\sprintf('+%d years', $warrantyYears)));
        $asset->setSupplier(self::SUPPLIERS[$this->faker->numberBetween(0, \count(self::SUPPLIERS) - 1)]);

        // Historique : entrée au parc, rangement, puis éventuels changements d'état
        $at = $purchasedAt->setTime(9, 30);
        $this->log($asset, AssetEventType::Created, 'Équipement ajouté à l’inventaire', $manager, $at);
        $this->log($asset, AssetEventType::Moved, 'Rangé : '.$room->getPath(), $manager, $at);

        if (AssetStatus::Available !== $status) {
            $at = $at->modify('+3 days');
            $this->log($asset, AssetEventType::StatusChanged, 'Mettre en service : Disponible → En service', $manager, $at);
        }

        $recent = \DateTimeImmutable::createFromMutable($this->faker->dateTimeBetween('-40 days', '-1 day'));
        match ($status) {
            AssetStatus::InMaintenance => $this->log($asset, AssetEventType::StatusChanged, 'Envoyer en maintenance : En service → En maintenance', $technician, $recent),
            AssetStatus::OutOfOrder => $this->log($asset, AssetEventType::StatusChanged, 'Déclarer hors service : En service → Hors service', $technician, $recent),
            AssetStatus::Retired => $this->retire($asset, $room, $manager, $technician, $recent),
            default => null,
        };

        save($asset);
    }

    private function retire(Asset $asset, Location $room, User $manager, User $technician, \DateTimeImmutable $at): void
    {
        $this->log($asset, AssetEventType::StatusChanged, 'Déclarer hors service : En service → Hors service', $technician, $at->modify('-20 days'));
        $this->log($asset, AssetEventType::StatusChanged, 'Réformer : Hors service → Réformé', $manager, $at);
        $this->log($asset, AssetEventType::Moved, 'Déplacé : '.$room->getPath().' → sans emplacement', $manager, $at);
    }

    private function log(Asset $asset, AssetEventType $type, string $summary, User $actor, \DateTimeImmutable $at): void
    {
        save(new AssetEvent($asset, $type, $summary, $actor, $at));
        $asset->touch($at);
    }

    private function pickStatus(\DateTimeImmutable $purchasedAt): AssetStatus
    {
        $old = $purchasedAt < new \DateTimeImmutable('-3 years');
        $roll = $this->faker->numberBetween(1, 100);

        return match (true) {
            $old && $roll <= 12 => AssetStatus::Retired,
            $roll <= 6 => AssetStatus::OutOfOrder,
            $roll <= 14 => AssetStatus::InMaintenance,
            $roll <= 34 => AssetStatus::Available,
            default => AssetStatus::InService,
        };
    }

    private function pickCondition(\DateTimeImmutable $purchasedAt, AssetStatus $status): AssetCondition
    {
        return match (true) {
            \in_array($status, [AssetStatus::OutOfOrder, AssetStatus::Retired], true) => AssetCondition::Damaged,
            $purchasedAt > new \DateTimeImmutable('-1 year') => AssetCondition::New,
            $purchasedAt < new \DateTimeImmutable('-3 years') => AssetCondition::Worn,
            default => AssetCondition::Good,
        };
    }
}
