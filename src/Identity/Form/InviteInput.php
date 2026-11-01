<?php

declare(strict_types=1);

namespace App\Identity\Form;

use App\Identity\Enum\Role;
use App\Identity\Validator\AvailableEmail;
use Symfony\Component\Validator\Constraints as Assert;

final class InviteInput
{
    #[Assert\NotBlank(message: 'Indiquez l’adresse e-mail.')]
    #[Assert\Email(message: 'Cette adresse e-mail n’est pas valide.')]
    #[Assert\Length(max: 180)]
    #[AvailableEmail]
    public ?string $email = null;

    #[Assert\NotBlank(message: 'Indiquez le prénom.')]
    #[Assert\Length(max: 80)]
    public ?string $firstName = null;

    #[Assert\NotBlank(message: 'Indiquez le nom.')]
    #[Assert\Length(max: 80)]
    public ?string $lastName = null;

    public Role $role = Role::Usager;
}
