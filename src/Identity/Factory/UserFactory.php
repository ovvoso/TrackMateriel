<?php

declare(strict_types=1);

namespace App\Identity\Factory;

use App\Identity\Entity\User;
use App\Identity\Enum\Role;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Zenstruck\Foundry\Object\Instantiator;
use Zenstruck\Foundry\Persistence\PersistentObjectFactory;

/**
 * @extends PersistentObjectFactory<User>
 */
final class UserFactory extends PersistentObjectFactory
{
    public const string DEFAULT_PASSWORD = 'Demo-Materiel-2026';

    public function __construct(private readonly UserPasswordHasherInterface $hasher)
    {
        parent::__construct();
    }

    public static function class(): string
    {
        return User::class;
    }

    /**
     * @return array<string, mixed>
     */
    protected function defaults(): array
    {
        return [
            'email' => self::faker()->unique()->safeEmail(),
            'firstName' => self::faker()->firstName(),
            'lastName' => self::faker()->lastName(),
            'role' => Role::Usager,
            'plainPassword' => self::DEFAULT_PASSWORD,
        ];
    }

    public function deactivated(): static
    {
        return $this->afterInstantiate(static fn (User $user) => $user->deactivate());
    }

    protected function initialize(): static
    {
        return $this
            ->instantiateWith(Instantiator::withConstructor()->allowExtra('plainPassword'))
            ->afterInstantiate(function (User $user, array $attributes): void {
                \assert(\is_string($attributes['plainPassword']));
                $user->setPassword($this->hasher->hashPassword($user, $attributes['plainPassword']));
            });
    }
}
