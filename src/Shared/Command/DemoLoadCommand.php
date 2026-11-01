<?php

declare(strict_types=1);

namespace App\Shared\Command;

use App\Identity\Repository\UserRepository;
use App\Shared\Doctrine\Sequences;
use Doctrine\DBAL\Connection;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\NullOutput;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Charge l'établissement de démonstration (comptes, parc, prêts).
 *
 *   --if-empty : ne fait rien si des comptes existent déjà (démarrage d'un conteneur)
 *   --force    : efface toutes les données puis recharge la démo (remise à zéro)
 */
#[AsCommand(name: 'app:demo:load', description: 'Charge les données de démonstration')]
final readonly class DemoLoadCommand
{
    /** Tables de données métier, vidées avant un rechargement */
    private const array TABLES = [
        'lending_loan_line', 'lending_loan',
        'inventory_asset_event', 'inventory_asset', 'inventory_category', 'inventory_location',
        'app_account_token', 'app_user',
    ];

    public function __construct(
        private Connection $connection,
        private UserRepository $users,
    ) {
    }

    public function __invoke(
        SymfonyStyle $io,
        Application $application,
        #[Option('Ne rien faire si la base contient déjà des comptes')] bool $ifEmpty = false,
        #[Option('Effacer les données existantes avant de recharger')] bool $force = false,
    ): int {
        $hasData = $this->users->count() > 0;

        if ($hasData && $ifEmpty) {
            $io->note('Des comptes existent déjà : données conservées.');

            return Command::SUCCESS;
        }

        if ($hasData && !$force) {
            $io->error('La base contient déjà des données. Relancez avec --force pour tout effacer, ou --if-empty.');

            return Command::FAILURE;
        }

        if ($hasData) {
            $this->connection->executeStatement('TRUNCATE '.implode(', ', self::TABLES).' CASCADE');
            foreach ([Sequences::ASSET_CODE, Sequences::LOAN_REFERENCE] as $sequence) {
                $this->connection->executeStatement(\sprintf('ALTER SEQUENCE %s RESTART WITH 1', $sequence));
            }
        }

        $application->setAutoExit(false);
        $status = $application->find('foundry:load-fixtures')->run(new ArrayInput(['name' => ['main'], '--append' => true]), new NullOutput());
        if (Command::SUCCESS !== $status) {
            $io->error('Le chargement de la démo a échoué.');

            return Command::FAILURE;
        }

        $io->success(\sprintf('Démo chargée : %d comptes, %d équipements, %d prêts.',
            $this->count('app_user'), $this->count('inventory_asset'), $this->count('lending_loan')));

        return Command::SUCCESS;
    }

    private function count(string $table): int
    {
        $value = $this->connection->fetchOne('SELECT COUNT(*) FROM '.$table);
        \assert(is_numeric($value));

        return (int) $value;
    }
}
