<?php

declare(strict_types=1);

namespace App\Tests\Inventory;

use App\Identity\Entity\User;
use App\Identity\Enum\Role;
use App\Identity\Factory\UserFactory;
use App\Inventory\Entity\Asset;
use App\Inventory\Entity\AssetEvent;
use App\Inventory\Repository\AssetEventRepository;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Zenstruck\Foundry\Test\Factories;

abstract class InventoryTestCase extends WebTestCase
{
    use Factories;

    protected KernelBrowser $client;

    protected function setUp(): void
    {
        $this->client = self::createClient();
    }

    protected function logInAs(Role $role): User
    {
        $user = UserFactory::createOne(['role' => $role]);
        $this->client->loginUser($user);

        return $user;
    }

    /**
     * @return list<string> résumés du journal, du plus récent au plus ancien
     */
    protected function history(Asset $asset): array
    {
        $events = self::getContainer()->get(AssetEventRepository::class)->findForAsset($asset);

        return array_map(static fn (AssetEvent $event) => $event->getSummary(), $events);
    }

    /**
     * Soumet le formulaire de changement d'état affiché sur la fiche.
     */
    protected function applyTransitionFromPage(Asset $asset, string $label): void
    {
        $crawler = $this->client->request('GET', '/inventaire/'.$asset->getCode());
        $this->client->submit($crawler->selectButton($label)->form());
    }
}
