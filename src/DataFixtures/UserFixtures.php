<?php

namespace App\DataFixtures;

use App\Entity\User;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class UserFixtures extends Fixture
{
    public function __construct(
        private UserPasswordHasherInterface $passwordHasher
    ) {
    }

    public function load(ObjectManager $manager): void
    {
        // Création de l'utilisateur Admin
        $admin = new User();
        $admin->setEmail('admin-moas@gmail.com');
        $admin->setUsername('admin-moas');
        $admin->setRoles(['ROLE_ADMIN']);
        $admin->setPassword($this->passwordHasher->hashPassword($admin, 'PASSER'));
        $manager->persist($admin);

        // Création de l'utilisateur standard
        $user = new User();
        $user->setEmail('charnomadjou@gmail.com');
        $user->setUsername('charnomadjou');
        $user->setRoles(['ROLE_USER']);
        $user->setPassword($this->passwordHasher->hashPassword($user, '1234'));
        $manager->persist($user);

        $manager->flush();
    }
}
