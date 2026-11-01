<?php

declare(strict_types=1);

namespace App\Story;

use App\Identity\Enum\Role;
use App\Identity\Factory\UserFactory;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Zenstruck\Foundry\Attribute\AsFixture;
use Zenstruck\Foundry\Story;

/**
 * Jeu de démonstration : des comptes pour chaque profil dans un lycée fictif,
 * puis son parc et ses prêts. Mot de passe commun : variable DEMO_PASSWORD.
 */
#[AsFixture(name: 'main')]
final class AppStory extends Story
{
    public const string MANAGER_EMAIL = 'moussa.ndiaye@lycee-demo.sn';
    public const string TECHNICIAN_EMAIL = 'fatou.sarr@lycee-demo.sn';
    public const string TEACHER_EMAIL = 'ibrahima.fall@lycee-demo.sn';

    /**
     * Un compte de référence par profil, proposés sur l'écran de connexion en mode démo.
     */
    public const array REFERENCE_ACCOUNTS = [
        'awa.diop@lycee-demo.sn' => Role::Admin,
        self::MANAGER_EMAIL => Role::Gestionnaire,
        self::TECHNICIAN_EMAIL => Role::Technicien,
        self::TEACHER_EMAIL => Role::Usager,
    ];

    public function __construct(
        #[Autowire(env: 'DEMO_PASSWORD')]
        private readonly string $password,
    ) {
    }

    public function build(): void
    {
        $users = UserFactory::new(['plainPassword' => $this->password]);

        $users->create(['email' => 'awa.diop@lycee-demo.sn', 'firstName' => 'Awa', 'lastName' => 'Diop', 'role' => Role::Admin]);
        $users->create(['email' => self::MANAGER_EMAIL, 'firstName' => 'Moussa', 'lastName' => 'Ndiaye', 'role' => Role::Gestionnaire]);
        $users->create(['email' => self::TECHNICIAN_EMAIL, 'firstName' => 'Fatou', 'lastName' => 'Sarr', 'role' => Role::Technicien]);
        $users->create(['email' => self::TEACHER_EMAIL, 'firstName' => 'Ibrahima', 'lastName' => 'Fall', 'role' => Role::Usager]);
        $users->create(['email' => 'mariama.ba@lycee-demo.sn', 'firstName' => 'Mariama', 'lastName' => 'Ba', 'role' => Role::Usager]);
        $users->create(['email' => 'ousmane.diallo@lycee-demo.sn', 'firstName' => 'Ousmane', 'lastName' => 'Diallo', 'role' => Role::Usager]);

        InventoryStory::load();
        LoanStory::load();
    }
}
