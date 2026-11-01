<?php

declare(strict_types=1);

namespace App\Story;

use App\Identity\Enum\Role;
use App\Identity\Factory\UserFactory;
use Zenstruck\Foundry\Attribute\AsFixture;
use Zenstruck\Foundry\Story;

/**
 * Jeu de démonstration : un compte par profil, dans un établissement fictif.
 * Mot de passe commun : UserFactory::DEFAULT_PASSWORD.
 */
#[AsFixture(name: 'main')]
final class AppStory extends Story
{
    public function build(): void
    {
        UserFactory::createOne(['email' => 'awa.diop@lycee-demo.sn', 'firstName' => 'Awa', 'lastName' => 'Diop', 'role' => Role::Admin]);
        UserFactory::createOne(['email' => 'moussa.ndiaye@lycee-demo.sn', 'firstName' => 'Moussa', 'lastName' => 'Ndiaye', 'role' => Role::Gestionnaire]);
        UserFactory::createOne(['email' => 'fatou.sarr@lycee-demo.sn', 'firstName' => 'Fatou', 'lastName' => 'Sarr', 'role' => Role::Technicien]);
        UserFactory::createOne(['email' => 'ibrahima.fall@lycee-demo.sn', 'firstName' => 'Ibrahima', 'lastName' => 'Fall', 'role' => Role::Usager]);
    }
}
