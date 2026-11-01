<?php

declare(strict_types=1);

namespace App\Lending\Service;

use App\Lending\Entity\Loan;
use App\Lending\Repository\LoanRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Clock\ClockInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Rappels de retour envoyés aux emprunteurs qui ont un compte. Prévu pour
 * être lancé une fois par jour (tâche planifiée).
 */
final readonly class LoanReminders
{
    public function __construct(
        private LoanRepository $loans,
        private MailerInterface $mailer,
        private UrlGeneratorInterface $urls,
        private EntityManagerInterface $entityManager,
        private ClockInterface $clock,
        private LoggerInterface $logger,
    ) {
    }

    /**
     * @return array{sent: int, failed: int}
     */
    public function send(): array
    {
        $now = $this->clock->now();
        $today = $now->setTime(0, 0);
        $result = ['sent' => 0, 'failed' => 0];

        foreach ($this->loans->findDueForReminder($today) as $loan) {
            try {
                $this->mailer->send($this->reminder($loan, $today));
                $loan->recordReminder($now);
                ++$result['sent'];
            } catch (TransportExceptionInterface $exception) {
                // Un échec n'empêche pas les autres rappels ; il sera retenté au prochain passage
                $this->logger->error('Rappel de prêt non envoyé', ['loan' => $loan->getReference(), 'exception' => $exception]);
                ++$result['failed'];
            }
        }

        $this->entityManager->flush();

        return $result;
    }

    private function reminder(Loan $loan, \DateTimeImmutable $today): TemplatedEmail
    {
        $account = $loan->getBorrower()->account;
        \assert(null !== $account);
        $late = $loan->daysLate($today);

        return (new TemplatedEmail())
            ->to(new Address($account->getEmail(), $account->getFullName()))
            ->subject($late > 0
                ? \sprintf('Matériel à rendre : %d jour%s de retard', $late, $late > 1 ? 's' : '')
                : 'Rappel : matériel à rendre demain')
            ->htmlTemplate('email/loan_reminder.html.twig')
            ->context([
                'loan' => $loan,
                'late' => $late,
                'url' => $this->urls->generate('app_loan_show', ['id' => $loan->getId()], UrlGeneratorInterface::ABSOLUTE_URL),
            ]);
    }
}
