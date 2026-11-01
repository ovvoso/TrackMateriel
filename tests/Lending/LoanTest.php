<?php

declare(strict_types=1);

namespace App\Tests\Lending;

use App\Identity\Entity\User;
use App\Identity\Enum\Role;
use App\Identity\Factory\UserFactory;
use App\Inventory\Entity\Asset;
use App\Inventory\Enum\AssetCondition;
use App\Inventory\Enum\AssetStatus;
use App\Inventory\Factory\AssetFactory;
use App\Inventory\Factory\CategoryFactory;
use App\Lending\Entity\Loan;
use App\Lending\Repository\LoanRepository;
use App\Tests\Inventory\InventoryTestCase;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DomCrawler\Field\ChoiceFormField;
use Symfony\Component\DomCrawler\Form;
use Symfony\Component\HttpFoundation\Response;

final class LoanTest extends InventoryTestCase
{
    public function testManagerLendsSeveralAssetsToATeacher(): void
    {
        $this->logInAs(Role::Gestionnaire);
        $teacher = UserFactory::createOne(['firstName' => 'Mariama', 'lastName' => 'Ba']);
        $first = AssetFactory::createOne(['name' => 'Microscope 1']);
        $second = AssetFactory::createOne(['name' => 'Microscope 2']);

        $this->lend([$first, $second], ['loan[account]' => $teacher->getId()->toRfc4122()]);

        self::assertResponseRedirects();
        $loan = $this->onlyLoan();
        self::assertMatchesRegularExpression('/^PRT-\d{4}-\d{5}$/', $loan->getReference());
        self::assertSame('Mariama Ba', $loan->getBorrower()->name);
        self::assertTrue($loan->getBorrower()->isAccount($teacher));
        self::assertSame(AssetStatus::OnLoan, $this->reload($first)->getStatus());
        self::assertStringStartsWith('Prêté à Mariama Ba jusqu’au', $this->history($first)[0]);
    }

    public function testAStudentWithoutAccountCanBorrow(): void
    {
        $this->logInAs(Role::Gestionnaire);
        $camera = AssetFactory::createOne(['name' => 'Appareil photo']);

        $this->lend([$camera], [
            'loan[borrowerType]' => 'person',
            'loan[personName]' => 'Aminata Sow',
            'loan[personGroup]' => 'Terminale S1',
        ]);

        self::assertResponseRedirects();
        self::assertSame('Aminata Sow · Terminale S1', $this->onlyLoan()->getBorrower()->describe());
    }

    public function testThePersonsNameIsRequiredWithoutAccount(): void
    {
        $this->logInAs(Role::Gestionnaire);
        $camera = AssetFactory::createOne();

        $this->lend([$camera], ['loan[borrowerType]' => 'person']);

        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        self::assertSelectorTextContains('.field-errors', 'Indiquez le nom de la personne qui emprunte');
        self::assertSame(AssetStatus::Available, $this->reload($camera)->getStatus());
    }

    public function testOnlyAvailableEquipmentIsOffered(): void
    {
        $this->logInAs(Role::Gestionnaire);
        AssetFactory::createOne(['name' => 'Tablette disponible']);
        AssetFactory::new()->withStatus(AssetStatus::InMaintenance)->create(['name' => 'Tablette en réparation']);

        $this->client->request('GET', '/prets/nouveau');

        self::assertSelectorTextContains('.checklist', 'Tablette disponible');
        self::assertSelectorTextNotContains('.checklist', 'Tablette en réparation');
    }

    public function testAnAssetCannotBeLentTwiceEvenFromAStalePage(): void
    {
        $this->logInAs(Role::Gestionnaire);
        $projector = AssetFactory::createOne();
        $teacher = UserFactory::createOne();

        // Le formulaire est ouvert avant que l'équipement ne soit prêté ailleurs
        $crawler = $this->client->request('GET', '/prets/nouveau');
        $form = $crawler->selectButton('Enregistrer le prêt')->form();
        $this->lend([$projector], ['loan[account]' => $teacher->getId()->toRfc4122()]);

        $this->tickAssets($form, [$projector]);
        $this->client->submit($form, ['loan[account]' => $teacher->getId()->toRfc4122()]);

        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        self::assertCount(1, self::getContainer()->get(LoanRepository::class)->findAll());
    }

    public function testPartialReturnKeepsTheLoanOpenUntilEverythingIsBack(): void
    {
        $this->logInAs(Role::Gestionnaire);
        $tablets = CategoryFactory::createOne(['name' => 'Tablette']);
        $first = AssetFactory::createOne(['name' => 'Tablette A', 'category' => $tablets]);
        $second = AssetFactory::createOne(['name' => 'Tablette B', 'category' => $tablets]);
        $this->lend([$first, $second], ['loan[account]' => UserFactory::createOne()->getId()->toRfc4122()]);
        $loan = $this->onlyLoan();

        // Premier retour : seule la tablette A revient, endommagée
        $crawler = $this->client->request('GET', '/prets/'.$loan->getId());
        $form = $crawler->selectButton('Enregistrer le retour')->form();
        $this->checkbox($form, 'form[lines][1][returned]')->untick();
        $form['form[lines][0][condition]'] = AssetCondition::Damaged->value;
        $this->client->submit($form);
        $this->client->followRedirect();

        self::assertSelectorTextContains('.alert-info', 'Tablette A (');
        self::assertSame(AssetStatus::Available, $this->reload($first)->getStatus());
        self::assertSame(AssetCondition::Damaged, $this->reload($first)->getCondition());
        self::assertSame(AssetStatus::OnLoan, $this->reload($second)->getStatus());
        self::assertFalse($this->onlyLoan()->isClosed());

        // Second retour : le prêt est clos
        $crawler = $this->client->request('GET', '/prets/'.$loan->getId());
        $this->client->submit($crawler->selectButton('Enregistrer le retour')->form());

        self::assertTrue($this->onlyLoan()->isClosed());
        self::assertSame(AssetStatus::Available, $this->reload($second)->getStatus());
    }

    public function testALoanCanBeExtended(): void
    {
        $this->logInAs(Role::Gestionnaire);
        $this->lend([AssetFactory::createOne()], ['loan[account]' => UserFactory::createOne()->getId()->toRfc4122()]);
        $loan = $this->onlyLoan();
        $newDue = $loan->getDueAt()->modify('+14 days');

        $crawler = $this->client->request('GET', '/prets/'.$loan->getId());
        $this->client->submit($crawler->selectButton('Prolonger')->form(['extend_loan[dueAt]' => $newDue->format('Y-m-d')]));

        self::assertResponseRedirects();
        self::assertEquals($newDue, $this->onlyLoan()->getDueAt());
    }

    public function testOverdueLoansHaveTheirOwnTab(): void
    {
        $this->logInAs(Role::Gestionnaire);
        $this->lend([AssetFactory::createOne()], [
            'loan[borrowerType]' => 'person',
            'loan[personName]' => 'Élève en retard',
        ]);
        $this->backdate($this->onlyLoan(), '-3 days');

        $this->client->request('GET', '/prets?etat=en-retard');

        self::assertSelectorTextContains('tbody', 'Élève en retard');
        self::assertSelectorTextContains('tbody', '3 jours de retard');
    }

    public function testLendingIsNotOfferedAsAPlainStatusButton(): void
    {
        $this->logInAs(Role::Gestionnaire);
        $asset = AssetFactory::createOne();

        $crawler = $this->client->request('GET', '/inventaire/'.$asset->getCode());
        self::assertSelectorExists('a:contains("Prêter")');

        $token = $crawler->filter('input[name="_token"]')->first()->attr('value');
        $this->client->request('POST', '/inventaire/'.$asset->getCode().'/etat/lend', ['_token' => $token]);

        self::assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
        self::assertSame(AssetStatus::Available, $this->reload($asset)->getStatus());
    }

    public function testBorrowersOnlySeeTheirOwnLoans(): void
    {
        $this->logInAs(Role::Gestionnaire);
        $borrower = UserFactory::createOne(['firstName' => 'Ibrahima', 'lastName' => 'Fall']);
        $other = UserFactory::createOne(['firstName' => 'Ousmane', 'lastName' => 'Diallo']);
        $this->lend([AssetFactory::createOne()], ['loan[account]' => $borrower->getId()->toRfc4122()]);
        $own = $this->onlyLoan();
        $this->lend([AssetFactory::createOne()], ['loan[account]' => $other->getId()->toRfc4122()]);
        $foreign = $this->loanOf($other);

        $this->client->loginUser($borrower);

        $this->client->request('GET', '/prets');
        self::assertSelectorTextContains('h1', 'Mes emprunts');
        self::assertSelectorTextContains('tbody', 'Ibrahima Fall');
        self::assertSelectorTextNotContains('tbody', 'Ousmane Diallo');

        $this->client->request('GET', '/prets/'.$own->getId());
        self::assertResponseIsSuccessful();
        self::assertSelectorNotExists('button:contains("Enregistrer le retour")');

        $this->client->request('GET', '/prets/'.$foreign->getId());
        self::assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);

        $this->client->request('GET', '/prets/nouveau');
        self::assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }

    /**
     * @param list<Asset>          $assets
     * @param array<string, mixed> $fields
     */
    private function lend(array $assets, array $fields): void
    {
        $crawler = $this->client->request('GET', '/prets/nouveau');
        $form = $crawler->selectButton('Enregistrer le prêt')->form();
        $this->tickAssets($form, $assets);

        $this->client->submit($form, $fields);
    }

    /**
     * @param list<Asset> $assets
     */
    private function tickAssets(Form $form, array $assets): void
    {
        $ids = array_map(static fn (Asset $asset) => $asset->getId()->toRfc4122(), $assets);

        foreach ($form->all() as $field) {
            if ($field instanceof ChoiceFormField && str_starts_with($field->getName(), 'loan[assets]') && \in_array($field->availableOptionValues()[0], $ids, true)) {
                $field->tick();
            }
        }
    }

    private function checkbox(Form $form, string $name): ChoiceFormField
    {
        $field = $form->get($name);
        self::assertInstanceOf(ChoiceFormField::class, $field);

        return $field;
    }

    private function onlyLoan(): Loan
    {
        $loans = self::getContainer()->get(LoanRepository::class)->findAll();
        self::assertCount(1, $loans);
        self::getContainer()->get(EntityManagerInterface::class)->refresh($loans[0]);

        return $loans[0];
    }

    private function loanOf(User $user): Loan
    {
        $loan = self::getContainer()->get(LoanRepository::class)->findOneBy(['borrowerAccount' => $user]);
        self::assertNotNull($loan);

        return $loan;
    }

    private function backdate(Loan $loan, string $due): void
    {
        self::getContainer()->get(EntityManagerInterface::class)->getConnection()->executeStatement(
            'UPDATE lending_loan SET due_at = :due WHERE id = :id',
            ['due' => (new \DateTimeImmutable($due))->format('Y-m-d'), 'id' => $loan->getId()->toRfc4122()],
        );
    }

    private function reload(Asset $asset): Asset
    {
        $fresh = AssetFactory::repository()->find($asset->getId());
        self::assertNotNull($fresh);
        self::getContainer()->get(EntityManagerInterface::class)->refresh($fresh);

        return $fresh;
    }
}
