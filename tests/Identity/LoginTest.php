<?php

declare(strict_types=1);

namespace App\Tests\Identity;

use App\Identity\Enum\Role;
use App\Identity\Factory\UserFactory;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;
use Zenstruck\Foundry\Test\Factories;

final class LoginTest extends WebTestCase
{
    use Factories;

    private KernelBrowser $client;

    protected function setUp(): void
    {
        $this->client = self::createClient();
    }

    public function testAnonymousVisitorIsSentToLogin(): void
    {
        $this->client->request('GET', '/');

        self::assertResponseRedirects('/connexion');
    }

    public function testValidCredentialsOpenTheDashboard(): void
    {
        UserFactory::createOne(['email' => 'awa.diop@lycee-demo.sn', 'firstName' => 'Awa', 'role' => Role::Admin]);

        $this->logIn('Awa.Diop@lycee-demo.sn', UserFactory::DEFAULT_PASSWORD);

        self::assertResponseRedirects('/');
        $this->client->followRedirect();
        self::assertSelectorTextContains('h1', 'Bonjour, Awa');
    }

    public function testLoginRecordsTheConnectionDate(): void
    {
        $user = UserFactory::createOne(['email' => 'moussa@lycee-demo.sn']);

        $this->logIn('moussa@lycee-demo.sn', UserFactory::DEFAULT_PASSWORD);

        self::assertNotNull(UserFactory::repository()->find($user->getId())?->getLastLoginAt());
    }

    public function testWrongPasswordIsRejected(): void
    {
        UserFactory::createOne(['email' => 'fatou@lycee-demo.sn']);

        $this->logIn('fatou@lycee-demo.sn', 'mauvais-mot-de-passe');

        self::assertResponseRedirects('/connexion');
        $this->client->followRedirect();
        self::assertSelectorExists('.alert-danger');
    }

    public function testDeactivatedAccountCannotLogIn(): void
    {
        UserFactory::new()->deactivated()->create(['email' => 'ancien@lycee-demo.sn']);

        $this->logIn('ancien@lycee-demo.sn', UserFactory::DEFAULT_PASSWORD);
        $this->client->followRedirect();

        self::assertSelectorTextContains('.alert-danger', 'Ce compte est désactivé');
    }

    public function testLoginIsThrottledAfterFiveFailures(): void
    {
        UserFactory::createOne(['email' => 'cible@lycee-demo.sn']);

        for ($i = 0; $i < 5; ++$i) {
            $this->logIn('cible@lycee-demo.sn', 'essai-'.$i);
        }
        $this->logIn('cible@lycee-demo.sn', UserFactory::DEFAULT_PASSWORD);
        $this->client->followRedirect();

        self::assertSelectorExists('.alert-danger');
        self::assertSelectorTextContains('.alert-danger', 'Trop de tentatives');
    }

    public function testLogoutRequiresPost(): void
    {
        $this->client->loginUser(UserFactory::createOne());

        $this->client->request('GET', '/deconnexion');

        self::assertResponseStatusCodeSame(Response::HTTP_METHOD_NOT_ALLOWED);
    }

    public function testLogoutFromTheSidebarEndsTheSession(): void
    {
        $this->client->loginUser(UserFactory::createOne());
        $crawler = $this->client->request('GET', '/');

        $this->client->submit($crawler->filter('form[action="/deconnexion"]')->form());
        self::assertResponseRedirects('/connexion');

        $this->client->request('GET', '/');
        self::assertResponseRedirects('/connexion');
    }

    public function testUserManagementIsHiddenFromNonAdmins(): void
    {
        $this->client->loginUser(UserFactory::createOne(['role' => Role::Technicien]));

        $this->client->request('GET', '/');

        self::assertSelectorTextNotContains('.nav', 'Utilisateurs');
    }

    private function logIn(string $email, string $password): void
    {
        $crawler = $this->client->request('GET', '/connexion');
        $this->client->submit($crawler->selectButton('Se connecter')->form([
            'email' => $email,
            'password' => $password,
        ]));
    }
}
