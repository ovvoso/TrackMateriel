<?php

declare(strict_types=1);

namespace App\Tests\Lending;

use App\Identity\Factory\UserFactory;
use App\Inventory\Factory\AssetFactory;
use App\Lending\Entity\Loan;
use App\Lending\Entity\LoanLine;
use App\Lending\Model\Borrower;
use App\Lending\Service\LoanReminders;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Mime\Email;
use Zenstruck\Foundry\Test\Factories;

final class LoanRemindersTest extends KernelTestCase
{
    use Factories;

    public function testBorrowersAreRemindedTheDayBeforeAndWhileLate(): void
    {
        $dueTomorrow = $this->loan(Borrower::account(UserFactory::createOne(['email' => 'demain@lycee-demo.sn'])), '+1 day');
        $late = $this->loan(Borrower::account(UserFactory::createOne(['email' => 'retard@lycee-demo.sn'])), '-4 days');
        $this->loan(Borrower::account(UserFactory::createOne(['email' => 'tranquille@lycee-demo.sn'])), '+5 days');
        $this->loan(Borrower::person('Élève sans compte', '2nde A'), '-4 days');

        $result = self::getContainer()->get(LoanReminders::class)->send();

        self::assertSame(['sent' => 2, 'failed' => 0], $result);
        $recipients = array_map(static fn (Email $email) => $email->getTo()[0]->getAddress(), array_values(array_filter(self::getMailerMessages(), static fn ($m) => $m instanceof Email)));
        sort($recipients);
        self::assertSame(['demain@lycee-demo.sn', 'retard@lycee-demo.sn'], $recipients);
        self::assertNotNull($dueTomorrow->getLastReminderAt());
        self::assertNotNull($late->getLastReminderAt());
    }

    public function testALateBorrowerIsNotRemindedEveryDay(): void
    {
        $late = $this->loan(Borrower::account(UserFactory::createOne()), '-4 days');
        $late->recordReminder(new \DateTimeImmutable('-1 day'));
        self::getContainer()->get(EntityManagerInterface::class)->flush();

        self::assertSame(['sent' => 0, 'failed' => 0], self::getContainer()->get(LoanReminders::class)->send());

        $late->recordReminder(new \DateTimeImmutable('-3 days'));
        self::getContainer()->get(EntityManagerInterface::class)->flush();

        self::assertSame(['sent' => 1, 'failed' => 0], self::getContainer()->get(LoanReminders::class)->send());
    }

    private function loan(Borrower $borrower, string $due): Loan
    {
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $loan = new Loan(
            'PRT-TEST-'.bin2hex(random_bytes(3)),
            $borrower,
            new \DateTimeImmutable('-10 days'),
            (new \DateTimeImmutable($due))->setTime(0, 0),
            null,
        );
        new LoanLine($loan, AssetFactory::createOne());
        $entityManager->persist($loan);
        foreach ($loan->getLines() as $line) {
            $entityManager->persist($line);
        }
        $entityManager->flush();

        return $loan;
    }
}
