<?php

declare(strict_types=1);

namespace App\Identity\Command;

use App\Identity\Entity\User;
use App\Identity\Enum\Role;
use App\Identity\Repository\UserRepository;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\StreamableInputInterface;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * Crée un compte depuis le terminal. Sert surtout au premier administrateur
 * d'une instance, l'inscription publique n'existant pas.
 */
#[AsCommand(name: 'app:user:create', description: 'Crée un compte (le mot de passe est demandé de façon masquée)')]
final readonly class CreateUserCommand
{
    private const int MIN_PASSWORD_LENGTH = 12;

    public function __construct(
        private UserRepository $users,
        private UserPasswordHasherInterface $hasher,
        private ValidatorInterface $validator,
    ) {
    }

    public function __invoke(
        SymfonyStyle $io,
        InputInterface $input,
        #[Argument('Adresse e-mail')] string $email,
        #[Argument('Prénom')] string $firstName,
        #[Argument('Nom')] string $lastName,
        #[Option('Profil : admin, gestionnaire, technicien ou usager')] string $role = 'usager',
    ): int {
        $profile = Role::tryFrom('ROLE_'.mb_strtoupper($role));
        if (null === $profile) {
            $io->error(\sprintf('Profil inconnu « %s ».', $role));

            return Command::INVALID;
        }

        $user = new User($email, $firstName, $lastName, $profile);

        $violations = $this->validator->validate($user);
        if (\count($violations) > 0) {
            foreach ($violations as $violation) {
                $io->error($violation->getPropertyPath().' : '.$violation->getMessage());
            }

            return Command::INVALID;
        }

        // Saisie masquée au terminal ; une entrée fournie par flux (tests, scripts) est lue telle quelle
        $fromTerminal = !$input instanceof StreamableInputInterface || null === $input->getStream();
        $password = $io->askQuestion((new Question('Mot de passe'))->setHidden($fromTerminal));
        if (!\is_string($password) || mb_strlen($password) < self::MIN_PASSWORD_LENGTH) {
            $io->error(\sprintf('Le mot de passe doit contenir au moins %d caractères.', self::MIN_PASSWORD_LENGTH));

            return Command::INVALID;
        }

        $user->setPassword($this->hasher->hashPassword($user, $password));
        $this->users->save($user);

        $io->success(\sprintf('Compte %s créé (%s).', $user->getEmail(), $profile->label()));

        return Command::SUCCESS;
    }
}
