<?php

declare(strict_types=1);

namespace App\Story;

use App\Identity\Enum\Role;
use App\Identity\Factory\UserFactory;
use Zenstruck\Foundry\Attribute\AsFixture;
use Zenstruck\Foundry\Story;

/**
 * Jeu de démonstration : des comptes pour chaque profil dans un lycée fictif,
 * puis son parc et ses prêts. Mot de passe commun : UserFactory::DEFAULT_PASSWORD.
 */
#[AsFixture(name: 'main')]
final class AppStory extends Story
{
    public const string MANAGER_EMAIL = 'moussa.ndiaye@lycee-demo.sn';
    public const string TECHNICIAN_EMAIL = 'fatou.sarr@lycee-demo.sn';
    public const string TEACHER_EMAIL = 'ibrahima.fall@lycee-demo.sn';

    public function build(): void
    {
        UserFactory::createOne(['email' => 'awa.diop@lycee-demo.sn', 'firstName' => 'Awa', 'lastName' => 'Diop', 'role' => Role::Admin]);
        UserFactory::createOne(['email' => self::MANAGER_EMAIL, 'firstName' => 'Moussa', 'lastName' => 'Ndiaye', 'role' => Role::Gestionnaire]);
        UserFactory::createOne(['email' => self::TECHNICIAN_EMAIL, 'firstName' => 'Fatou', 'lastName' => 'Sarr', 'role' => Role::Technicien]);
        UserFactory::createOne(['email' => self::TEACHER_EMAIL, 'firstName' => 'Ibrahima', 'lastName' => 'Fall', 'role' => Role::Usager]);
        UserFactory::createOne(['email' => 'mariama.ba@lycee-demo.sn', 'firstName' => 'Mariama', 'lastName' => 'Ba', 'role' => Role::Usager]);
        UserFactory::createOne(['email' => 'ousmane.diallo@lycee-demo.sn', 'firstName' => 'Ousmane', 'lastName' => 'Diallo', 'role' => Role::Usager]);

        InventoryStory::load();
        LoanStory::load();
    }
}
