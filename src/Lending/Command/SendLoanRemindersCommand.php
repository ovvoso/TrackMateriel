<?php

declare(strict_types=1);

namespace App\Lending\Command;

use App\Lending\Service\LoanReminders;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * À planifier une fois par jour, par exemple à 7 h :
 *   php bin/console app:loans:remind
 */
#[AsCommand(name: 'app:loans:remind', description: 'Envoie les rappels de retour (veille de l’échéance, puis tous les trois jours de retard)')]
final readonly class SendLoanRemindersCommand
{
    public function __construct(private LoanReminders $reminders)
    {
    }

    public function __invoke(SymfonyStyle $io): int
    {
        $result = $this->reminders->send();

        if (0 === $result['sent'] + $result['failed']) {
            $io->success('Aucun rappel à envoyer aujourd’hui.');

            return Command::SUCCESS;
        }

        $io->success(\sprintf('%d rappel%s envoyé%s.', $result['sent'], $result['sent'] > 1 ? 's' : '', $result['sent'] > 1 ? 's' : ''));
        if ($result['failed'] > 0) {
            $io->error(\sprintf('%d rappel%s non envoyé%s : voir les journaux.', $result['failed'], $result['failed'] > 1 ? 's' : '', $result['failed'] > 1 ? 's' : ''));

            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }
}
