<?php

declare(strict_types=1);

namespace App\Tests\Identity;

use App\Identity\Enum\Role;
use App\Identity\Factory\UserFactory;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mime\Email;
use Zenstruck\Foundry\Test\Factories;

final class AccountAccessTest extends WebTestCase
{
    use Factories;

    private const string NEW_PASSWORD = 'cartable vert du jeudi matin';

    private KernelBrowser $client;

    protected function setUp(): void
    {
        $this->client = self::createClient();
        self::getContainer()->get('cache.rate_limiter')->clear();
    }

    public function testAnInvitedPersonActivatesTheirAccountAndLogsIn(): void
    {
        $this->client->loginUser(UserFactory::createOne(['role' => Role::Admin, 'firstName' => 'Awa', 'lastName' => 'Diop']));

        $crawler = $this->client->request('GET', '/utilisateurs/inviter');
        $this->client->submit($crawler->selectButton('Envoyer l’invitation')->form([
            'invite[firstName]' => 'Mariama',
            'invite[lastName]' => 'Ba',
            'invite[email]' => 'Mariama.Ba@Lycee-Demo.sn',
            'invite[role]' => Role::Usager->value,
        ]));

        self::assertResponseRedirects('/utilisateurs');
        self::assertEmailCount(1);
        $email = $this->lastEmail();
        self::assertEmailAddressContains($email, 'To', 'mariama.ba@lycee-demo.sn');
        self::assertEmailHtmlBodyContains($email, 'Awa Diop vous a ouvert');

        // Tant que l'invitation n'est pas acceptée, la connexion est refusée
        $link = $this->linkIn($email, '/compte/activer/');
        $this->client->restart();
        self::getContainer()->get('cache.rate_limiter')->clear();
        $this->logIn('mariama.ba@lycee-demo.sn', 'un essai au hasard');
        $this->client->followRedirect();
        self::assertSelectorTextContains('.alert-danger', 'pas encore activé');

        $crawler = $this->client->request('GET', $link);
        self::assertSelectorTextContains('h1', 'Bienvenue, Mariama');
        $this->client->submit($crawler->selectButton('Activer mon compte')->form([
            'new_password[password][first]' => self::NEW_PASSWORD,
            'new_password[password][second]' => self::NEW_PASSWORD,
        ]));
        self::assertResponseRedirects('/connexion?email=mariama.ba@lycee-demo.sn');

        $this->logIn('mariama.ba@lycee-demo.sn', self::NEW_PASSWORD);
        self::assertResponseRedirects('/');

        // Le lien ne sert qu'une fois
        $this->client->request('GET', $link);
        self::assertResponseStatusCodeSame(Response::HTTP_GONE);
    }

    public function testAWeakPasswordIsRefused(): void
    {
        $this->client->loginUser(UserFactory::createOne(['role' => Role::Admin]));
        $this->invite('tablette@lycee-demo.sn');

        $crawler = $this->client->request('GET', $this->linkIn($this->lastEmail(), '/compte/activer/'));
        $this->client->submit($crawler->selectButton('Activer mon compte')->form([
            'new_password[password][first]' => 'azertyuiop12',
            'new_password[password][second]' => 'azertyuiop12',
        ]));

        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        self::assertSelectorTextContains('.field-errors', 'trop facile à deviner');
    }

    public function testAnExpiredInvitationNoLongerWorks(): void
    {
        $this->client->loginUser(UserFactory::createOne(['role' => Role::Admin]));
        $this->invite('retardataire@lycee-demo.sn');
        self::getContainer()->get(EntityManagerInterface::class)->getConnection()
            ->executeStatement("UPDATE app_account_token SET expires_at = NOW() - INTERVAL '1 minute'");

        $this->client->request('GET', $this->linkIn($this->lastEmail(), '/compte/activer/'));

        self::assertResponseStatusCodeSame(Response::HTTP_GONE);
        self::assertSelectorTextContains('h1', 'Ce lien n’est plus valable');
    }

    public function testResendingAnInvitationInvalidatesThePreviousLink(): void
    {
        $this->client->loginUser(UserFactory::createOne(['role' => Role::Admin]));
        $this->invite('oublieux@lycee-demo.sn');
        $first = $this->linkIn($this->lastEmail(), '/compte/activer/');
        $user = UserFactory::repository()->findOneBy(['email' => 'oublieux@lycee-demo.sn']);
        self::assertNotNull($user);

        $crawler = $this->client->request('GET', '/utilisateurs/'.$user->getId());
        $this->client->submit($crawler->selectButton('Renvoyer l’invitation')->form());
        $second = $this->linkIn($this->lastEmail(), '/compte/activer/');

        $this->client->request('GET', $first);
        self::assertResponseStatusCodeSame(Response::HTTP_GONE);
        $this->client->request('GET', $second);
        self::assertResponseIsSuccessful();
    }

    public function testForgottenPasswordSendsASingleUseLink(): void
    {
        UserFactory::createOne(['email' => 'fatou@lycee-demo.sn']);

        $this->requestReset('fatou@lycee-demo.sn');
        self::assertResponseRedirects('/mot-de-passe-oublie/envoye');
        self::assertEmailCount(1);
        $link = $this->linkIn($this->lastEmail(), '/compte/reinitialiser/');

        $crawler = $this->client->request('GET', $link);
        $this->client->submit($crawler->selectButton('Enregistrer le mot de passe')->form([
            'new_password[password][first]' => self::NEW_PASSWORD,
            'new_password[password][second]' => self::NEW_PASSWORD,
        ]));
        self::assertResponseRedirects();

        $this->logIn('fatou@lycee-demo.sn', UserFactory::DEFAULT_PASSWORD);
        self::assertResponseRedirects('/connexion');
        $this->logIn('fatou@lycee-demo.sn', self::NEW_PASSWORD);
        self::assertResponseRedirects('/');

        $this->client->request('GET', $link);
        self::assertResponseStatusCodeSame(Response::HTTP_GONE);
    }

    public function testTheAnswerNeverRevealsWhetherAnAccountExists(): void
    {
        UserFactory::new()->deactivated()->create(['email' => 'parti@lycee-demo.sn']);

        $this->requestReset('inconnu@lycee-demo.sn');
        self::assertResponseRedirects('/mot-de-passe-oublie/envoye');
        $this->requestReset('parti@lycee-demo.sn');
        self::assertResponseRedirects('/mot-de-passe-oublie/envoye');

        self::assertEmailCount(0);
    }

    public function testResetRequestsAreThrottled(): void
    {
        UserFactory::createOne(['email' => 'cible@lycee-demo.sn']);

        for ($i = 0; $i < 4; ++$i) {
            $this->requestReset('cible@lycee-demo.sn');
        }

        // La 4e demande reçoit la même réponse, mais aucun e-mail ne part
        self::assertResponseRedirects('/mot-de-passe-oublie/envoye');
        self::assertEmailCount(0);
    }

    private function invite(string $email): void
    {
        $crawler = $this->client->request('GET', '/utilisateurs/inviter');
        $this->client->submit($crawler->selectButton('Envoyer l’invitation')->form([
            'invite[firstName]' => 'Prénom',
            'invite[lastName]' => 'Nom',
            'invite[email]' => $email,
        ]));
    }

    private function requestReset(string $email): void
    {
        $crawler = $this->client->request('GET', '/mot-de-passe-oublie');
        $this->client->submit($crawler->selectButton('Recevoir le lien')->form(['form[email]' => $email]));
    }

    private function logIn(string $email, string $password): void
    {
        $crawler = $this->client->request('GET', '/connexion');
        $this->client->submit($crawler->selectButton('Se connecter')->form(['email' => $email, 'password' => $password]));
    }

    private function lastEmail(): Email
    {
        $email = self::getMailerMessage();
        self::assertInstanceOf(Email::class, $email);

        return $email;
    }

    private function linkIn(Email $email, string $path): string
    {
        if (1 !== preg_match('#https?://[^/"]+('.preg_quote($path, '#').'[^"<\s]+)#', (string) $email->getHtmlBody(), $match)) {
            self::fail(\sprintf('Aucun lien « %s » dans l’e-mail.', $path));
        }

        return $match[1];
    }
}
