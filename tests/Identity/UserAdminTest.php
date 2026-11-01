<?php

declare(strict_types=1);

namespace App\Tests\Identity;

use App\Identity\Enum\Role;
use App\Identity\Factory\UserFactory;
use App\Identity\Service\AccountException;
use App\Identity\Service\AccountManager;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;
use Zenstruck\Foundry\Test\Factories;

final class UserAdminTest extends WebTestCase
{
    use Factories;

    private KernelBrowser $client;

    protected function setUp(): void
    {
        $this->client = self::createClient();
    }

    /**
     * @return iterable<string, array{Role}>
     */
    public static function nonAdmins(): iterable
    {
        yield 'gestionnaire' => [Role::Gestionnaire];
        yield 'technicien' => [Role::Technicien];
        yield 'usager' => [Role::Usager];
    }

    #[DataProvider('nonAdmins')]
    public function testOnlyAdministratorsManageAccounts(Role $role): void
    {
        $this->client->loginUser(UserFactory::createOne(['role' => $role]));

        $this->client->request('GET', '/utilisateurs');

        self::assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }

    public function testAnEmailCannotBeInvitedTwice(): void
    {
        $this->client->loginUser(UserFactory::createOne(['role' => Role::Admin]));
        UserFactory::createOne(['email' => 'deja@lycee-demo.sn']);

        $crawler = $this->client->request('GET', '/utilisateurs/inviter');
        $this->client->submit($crawler->selectButton('Envoyer l’invitation')->form([
            'invite[firstName]' => 'Déjà',
            'invite[lastName]' => 'Inscrit',
            'invite[email]' => 'DEJA@lycee-demo.sn',
        ]));

        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        self::assertSelectorTextContains('.field-errors', 'Un compte existe déjà');
    }

    public function testAnAdministratorCannotLockThemselvesOut(): void
    {
        $admin = UserFactory::createOne(['role' => Role::Admin]);
        $this->client->loginUser($admin);

        $this->client->request('GET', '/utilisateurs/'.$admin->getId());

        self::assertSelectorNotExists('button:contains("Désactiver le compte")');
        self::assertSelectorExists('select[name="user_edit[role]"][disabled]');
    }

    public function testTheServiceRefusesToRemoveTheLastActiveAdministrator(): void
    {
        $onlyAdmin = UserFactory::createOne(['role' => Role::Admin]);
        $someoneElse = UserFactory::createOne(['role' => Role::Gestionnaire]);
        $accounts = self::getContainer()->get(AccountManager::class);

        $this->expectException(AccountException::class);
        $this->expectExceptionMessage('dernier administrateur actif');

        $accounts->deactivate($onlyAdmin, $someoneElse);
    }

    public function testDeactivatingAnAccountRevokesAccess(): void
    {
        $this->client->loginUser(UserFactory::createOne(['role' => Role::Admin]));
        $teacher = UserFactory::createOne(['firstName' => 'Ousmane', 'lastName' => 'Diallo']);

        $crawler = $this->client->request('GET', '/utilisateurs/'.$teacher->getId());
        $this->client->submit($crawler->selectButton('Désactiver le compte')->form());

        self::assertFalse(UserFactory::repository()->find($teacher->getId())?->isActive());

        $this->client->request('GET', '/utilisateurs?statut=desactives');
        self::assertSelectorTextContains('tbody', 'Diallo Ousmane');
    }

    public function testChangingOwnPasswordRequiresTheCurrentOneAndKeepsTheSession(): void
    {
        $this->client->loginUser(UserFactory::createOne());

        $crawler = $this->client->request('GET', '/mon-compte');
        $this->client->submit($crawler->selectButton('Changer le mot de passe')->form([
            'change_password[currentPassword]' => 'pas le bon',
            'change_password[password][first]' => 'cartable vert du jeudi matin',
            'change_password[password][second]' => 'cartable vert du jeudi matin',
        ]));
        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        self::assertSelectorTextContains('.field-errors', 'Le mot de passe actuel est incorrect');

        $crawler = $this->client->request('GET', '/mon-compte');
        $this->client->submit($crawler->selectButton('Changer le mot de passe')->form([
            'change_password[currentPassword]' => UserFactory::DEFAULT_PASSWORD,
            'change_password[password][first]' => 'cartable vert du jeudi matin',
            'change_password[password][second]' => 'cartable vert du jeudi matin',
        ]));
        self::assertResponseRedirects('/mon-compte');

        $this->client->followRedirect();
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('.alert-success', 'Mot de passe modifié');
    }
}
