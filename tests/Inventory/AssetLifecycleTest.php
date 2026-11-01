<?php

declare(strict_types=1);

namespace App\Tests\Inventory;

use App\Identity\Enum\Role;
use App\Inventory\Entity\Asset;
use App\Inventory\Enum\AssetStatus;
use App\Inventory\Factory\AssetFactory;
use Symfony\Component\HttpFoundation\Response;

final class AssetLifecycleTest extends InventoryTestCase
{
    public function testTechnicianSendsAnAssetToMaintenance(): void
    {
        $this->logInAs(Role::Technicien);
        $asset = AssetFactory::new()->withStatus(AssetStatus::InService)->create();

        $this->applyTransitionFromPage($asset, 'Envoyer en maintenance');

        self::assertResponseRedirects('/inventaire/'.$asset->getCode());
        self::assertSame(AssetStatus::InMaintenance, $this->reload($asset)->getStatus());
        self::assertSame('Envoyer en maintenance : En service → En maintenance', $this->history($asset)[0]);
    }

    public function testTechnicianOnlySeesMaintenanceActions(): void
    {
        $this->logInAs(Role::Technicien);
        $asset = AssetFactory::new()->withStatus(AssetStatus::Available)->create();

        $this->client->request('GET', '/inventaire/'.$asset->getCode());

        self::assertSelectorExists('button:contains("Envoyer en maintenance")');
        self::assertSelectorNotExists('button:contains("Mettre en service")');
        self::assertSelectorNotExists('button:contains("Réformer")');
        self::assertSelectorNotExists('a:contains("Modifier")');
    }

    public function testTechnicianCannotRetireAnAssetEvenByForgingTheRequest(): void
    {
        $this->logInAs(Role::Technicien);
        $asset = AssetFactory::new()->withStatus(AssetStatus::Available)->create();

        // Jeton valide récupéré sur la fiche, transition interdite au profil
        $crawler = $this->client->request('GET', '/inventaire/'.$asset->getCode());
        $token = $crawler->filter('input[name="_token"]')->first()->attr('value');
        $this->client->request('POST', '/inventaire/'.$asset->getCode().'/etat/retire', ['_token' => $token]);

        self::assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
        self::assertSame(AssetStatus::Available, $this->reload($asset)->getStatus());
    }

    public function testManagerRetiresAnOutOfOrderAsset(): void
    {
        $this->logInAs(Role::Gestionnaire);
        $asset = AssetFactory::new()->withStatus(AssetStatus::OutOfOrder)->create();

        $this->applyTransitionFromPage($asset, 'Réformer');

        self::assertSame(AssetStatus::Retired, $this->reload($asset)->getStatus());
    }

    public function testATransitionMustMatchTheCurrentState(): void
    {
        $this->logInAs(Role::Gestionnaire);
        $asset = AssetFactory::new()->withStatus(AssetStatus::InService)->create();

        $crawler = $this->client->request('GET', '/inventaire/'.$asset->getCode());
        $token = $crawler->filter('input[name="_token"]')->first()->attr('value');
        $this->client->request('POST', '/inventaire/'.$asset->getCode().'/etat/return_from_maintenance', ['_token' => $token]);

        self::assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }

    public function testATransitionWithoutCsrfTokenIsRejected(): void
    {
        $this->logInAs(Role::Gestionnaire);
        $asset = AssetFactory::new()->withStatus(AssetStatus::Available)->create();

        $this->client->request('POST', '/inventaire/'.$asset->getCode().'/etat/put_in_service');

        // Jeton absent : le pare-feu renvoie vers la connexion, rien n'est modifié
        self::assertResponseRedirects('/connexion');
        self::assertSame(AssetStatus::Available, $this->reload($asset)->getStatus());
    }

    public function testARetiredAssetCanNoLongerBeEdited(): void
    {
        $this->logInAs(Role::Admin);
        $asset = AssetFactory::new()->withStatus(AssetStatus::Retired)->create();

        $this->client->request('GET', '/inventaire/'.$asset->getCode().'/modifier');

        self::assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }

    private function reload(Asset $asset): Asset
    {
        $fresh = AssetFactory::repository()->find($asset->getId());
        self::assertNotNull($fresh);

        return $fresh;
    }
}
