<?php

declare(strict_types=1);

namespace App\Tests\Inventory;

use App\Identity\Enum\Role;
use App\Inventory\Enum\LocationKind;
use App\Inventory\Factory\AssetFactory;
use App\Inventory\Factory\CategoryFactory;
use App\Inventory\Factory\LocationFactory;
use Symfony\Component\HttpFoundation\Response;

final class AssetManagementTest extends InventoryTestCase
{
    public function testManagerAddsAnAssetWhichReceivesAnInventoryCode(): void
    {
        $this->logInAs(Role::Gestionnaire);
        $category = CategoryFactory::createOne(['name' => 'Vidéoprojecteur']);
        $campus = LocationFactory::createOne(['name' => 'Campus']);
        $building = LocationFactory::new()->building($campus, 'Bâtiment B')->create();
        $room = LocationFactory::new()->room($building, 'Salle 104')->create();

        $crawler = $this->client->request('GET', '/inventaire/nouveau');
        $this->client->submit($crawler->selectButton('Ajouter à l’inventaire')->form([
            'asset[name]' => 'Vidéoprojecteur salle 104',
            'asset[category]' => $category->getId()->toRfc4122(),
            'asset[brand]' => 'Epson',
            'asset[serialNumber]' => '  X5JK-0042  ',
            'asset[location]' => $room->getId()->toRfc4122(),
            'asset[purchasePrice]' => '640000',
        ]));

        self::assertResponseRedirects();
        $asset = AssetFactory::repository()->findOneBy(['name' => 'Vidéoprojecteur salle 104']);
        self::assertNotNull($asset);
        self::assertMatchesRegularExpression('/^INV-\d{4}-\d{5}$/', $asset->getCode());
        self::assertSame('X5JK-0042', $asset->getSerialNumber());
        self::assertSame(['Rangé : Campus › Bâtiment B › Salle 104', 'Équipement ajouté à l’inventaire'], $this->history($asset));
    }

    public function testTwoAssetsCannotShareASerialNumber(): void
    {
        $this->logInAs(Role::Gestionnaire);
        $existing = AssetFactory::createOne(['serialNumber' => 'SN-001']);

        $crawler = $this->client->request('GET', '/inventaire/nouveau');
        $this->client->submit($crawler->selectButton('Ajouter à l’inventaire')->form([
            'asset[name]' => 'Doublon',
            'asset[category]' => $existing->getCategory()->getId()->toRfc4122(),
            'asset[serialNumber]' => 'SN-001',
        ]));

        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        self::assertSelectorTextContains('.field-errors', $existing->getCode());
    }

    public function testMovingAnAssetIsRecordedSeparatelyFromOtherChanges(): void
    {
        $this->logInAs(Role::Gestionnaire);
        $site = LocationFactory::createOne(['name' => 'Campus']);
        $from = LocationFactory::new()->building($site, 'Bâtiment A')->create();
        $to = LocationFactory::new()->building($site, 'Bâtiment C')->create();
        $asset = AssetFactory::createOne(['name' => 'Tablette', 'location' => $from]);

        $crawler = $this->client->request('GET', '/inventaire/'.$asset->getCode().'/modifier');
        $this->client->submit($crawler->selectButton('Enregistrer')->form([
            'asset[location]' => $to->getId()->toRfc4122(),
            'asset[brand]' => 'Apple',
        ]));

        self::assertResponseRedirects('/inventaire/'.$asset->getCode());
        self::assertSame([
            'Fiche modifiée : marque',
            'Déplacé : Campus › Bâtiment A → Campus › Bâtiment C',
        ], $this->history($asset));
    }

    public function testSavingWithoutChangesLeavesTheHistoryUntouched(): void
    {
        $this->logInAs(Role::Gestionnaire);
        $asset = AssetFactory::createOne(['purchasePrice' => '1500.00']);

        $crawler = $this->client->request('GET', '/inventaire/'.$asset->getCode().'/modifier');
        $this->client->submit($crawler->selectButton('Enregistrer')->form());

        self::assertResponseRedirects();
        self::assertSame([], $this->history($asset));
    }

    public function testRoomsCannotBeAttachedDirectlyToASite(): void
    {
        $this->logInAs(Role::Gestionnaire);
        $site = LocationFactory::createOne(['name' => 'Campus']);

        $crawler = $this->client->request('GET', '/emplacements/nouveau');
        $this->client->submit($crawler->selectButton('Enregistrer')->form([
            'location[kind]' => LocationKind::Room->value,
            'location[name]' => 'Salle 12',
            'location[parent]' => $site->getId()->toRfc4122(),
        ]));

        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        self::assertSelectorTextContains('.field-errors', 'doit être rattaché à un emplacement de type « Bâtiment »');
    }

    public function testALocationHoldingEquipmentCannotBeDeleted(): void
    {
        $this->logInAs(Role::Gestionnaire);
        $site = LocationFactory::createOne(['name' => 'Campus']);
        AssetFactory::createOne(['location' => $site]);

        $crawler = $this->client->request('GET', '/emplacements');
        $this->client->submit($crawler->selectButton('Supprimer')->form());
        $this->client->followRedirect();

        self::assertSelectorTextContains('.alert-danger', 'videz-le avant de le supprimer');
        self::assertCount(1, LocationFactory::all());
    }

    public function testAnEmptyLocationCanBeDeleted(): void
    {
        $this->logInAs(Role::Gestionnaire);
        LocationFactory::createOne(['name' => 'Ancien site']);

        $crawler = $this->client->request('GET', '/emplacements');
        $this->client->submit($crawler->selectButton('Supprimer')->form());

        self::assertResponseRedirects('/emplacements');
        self::assertCount(0, LocationFactory::all());
    }
}
