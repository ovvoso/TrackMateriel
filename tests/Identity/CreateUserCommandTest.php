<?php

declare(strict_types=1);

namespace App\Tests\Identity;

use App\Identity\Enum\Role;
use App\Identity\Factory\UserFactory;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Zenstruck\Foundry\Test\Factories;

final class CreateUserCommandTest extends KernelTestCase
{
    use Factories;

    private CommandTester $tester;

    protected function setUp(): void
    {
        $application = new Application(self::bootKernel());
        $this->tester = new CommandTester($application->find('app:user:create'));
    }

    public function testCreatesAnAdministrator(): void
    {
        $this->tester->setInputs(['un-mot-de-passe-solide']);

        $status = $this->tester->execute([
            'email' => 'Directeur@Lycee-Demo.sn',
            'first-name' => 'Cheikh',
            'last-name' => 'Ba',
            '--role' => 'admin',
        ]);

        self::assertSame(Command::SUCCESS, $status, $this->tester->getDisplay());
        $user = UserFactory::repository()->findOneBy(['email' => 'directeur@lycee-demo.sn']);
        self::assertNotNull($user);
        self::assertSame(Role::Admin, $user->getRole());
        self::assertNotSame('un-mot-de-passe-solide', $user->getPassword());
    }

    public function testRejectsAShortPassword(): void
    {
        $this->tester->setInputs(['court']);

        $status = $this->tester->execute(['email' => 'a@lycee-demo.sn', 'first-name' => 'A', 'last-name' => 'B']);

        self::assertSame(Command::INVALID, $status);
        UserFactory::assert()->count(0);
    }

    public function testRejectsAnUnknownRole(): void
    {
        $status = $this->tester->execute(['email' => 'a@lycee-demo.sn', 'first-name' => 'A', 'last-name' => 'B', '--role' => 'directeur']);

        self::assertSame(Command::INVALID, $status);
    }

    public function testRejectsADuplicateEmail(): void
    {
        UserFactory::createOne(['email' => 'deja@lycee-demo.sn']);

        $status = $this->tester->execute(['email' => 'deja@lycee-demo.sn', 'first-name' => 'A', 'last-name' => 'B']);

        self::assertSame(Command::INVALID, $status);
    }
}
