<?php

declare(strict_types=1);

namespace App\Tests\Inventory;

use App\Identity\Enum\Role;
use App\Inventory\Enum\AssetStatus;
use App\Inventory\Factory\AssetFactory;
use App\Inventory\Factory\LocationFactory;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\HttpFoundation\Response;

final class InventoryAccessTest extends InventoryTestCase
{
    public function testAnyAccountCanBrowseTheInventory(): void
    {
        $this->logInAs(Role::Usager);
        $asset = AssetFactory::createOne(['name' => 'Microscope optique', 'purchasePrice' => '120000']);

        $this->client->request('GET', '/inventaire');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('table', 'Microscope optique');
        self::assertSelectorNotExists('a:contains("Nouvel équipement")');

        $this->client->request('GET', '/inventaire/'.$asset->getCode());
        self::assertResponseIsSuccessful();
        self::assertSelectorNotExists('#achat');
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function managerOnlyPages(): iterable
    {
        yield 'nouvel équipement' => ['/inventaire/nouveau'];
        yield 'export' => ['/inventaire/export.csv'];
        yield 'catégories' => ['/categories'];
        yield 'nouvel emplacement' => ['/emplacements/nouveau'];
    }

    #[DataProvider('managerOnlyPages')]
    public function testUsersAndTechniciansCannotManageTheInventory(string $url): void
    {
        $this->logInAs(Role::Technicien);

        $this->client->request('GET', $url);

        self::assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }

    public function testSearchCombinesTextAndStatus(): void
    {
        $this->logInAs(Role::Usager);
        AssetFactory::new()->withStatus(AssetStatus::InMaintenance)->create(['name' => 'Tablette élève', 'brand' => 'Samsung']);
        AssetFactory::new()->withStatus(AssetStatus::Available)->create(['name' => 'Tablette de lecture', 'brand' => 'Apple']);
        AssetFactory::new()->withStatus(AssetStatus::InMaintenance)->create(['name' => 'Imprimante laser']);

        $crawler = $this->client->request('GET', '/inventaire?q=tablette&etat=in_maintenance');

        self::assertCount(1, $crawler->filter('tbody tr'));
        self::assertSelectorTextContains('tbody', 'Tablette élève');
    }

    public function testFilteringOnABuildingIncludesItsRooms(): void
    {
        $this->logInAs(Role::Usager);
        $site = LocationFactory::createOne(['name' => 'Campus']);
        $building = LocationFactory::new()->building($site, 'Bâtiment B')->create();
        $room = LocationFactory::new()->room($building, 'Salle B12')->create();
        $elsewhere = LocationFactory::new()->building($site, 'Bâtiment C')->create();
        AssetFactory::createOne(['name' => 'Vidéoprojecteur B12', 'location' => $room]);
        AssetFactory::createOne(['name' => 'Poste bâtiment C', 'location' => $elsewhere]);

        $crawler = $this->client->request('GET', '/inventaire?emplacement='.$building->getId()->toRfc4122());

        self::assertCount(1, $crawler->filter('tbody tr'));
        self::assertSelectorTextContains('tbody', 'Vidéoprojecteur B12');
    }

    public function testExportFollowsTheCurrentFilters(): void
    {
        $this->logInAs(Role::Gestionnaire);
        AssetFactory::new()->withStatus(AssetStatus::OutOfOrder)->create(['name' => 'Écran cassé']);
        AssetFactory::new()->withStatus(AssetStatus::Available)->create(['name' => 'Écran neuf']);

        $this->client->request('GET', '/inventaire/export.csv?etat=out_of_order');
        $csv = $this->client->getInternalResponse()->getContent();

        self::assertResponseHeaderSame('Content-Type', 'text/csv; charset=UTF-8');
        self::assertStringStartsWith("\u{FEFF}Code;Désignation;", $csv);
        self::assertStringContainsString('Écran cassé', $csv);
        self::assertStringNotContainsString('Écran neuf', $csv);
    }
}
