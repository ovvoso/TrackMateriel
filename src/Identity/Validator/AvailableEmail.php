<?php

declare(strict_types=1);

namespace App\Identity\Validator;

use Symfony\Component\Validator\Constraint;

#[\Attribute(\Attribute::TARGET_PROPERTY)]
final class AvailableEmail extends Constraint
{
    public string $message = 'Un compte existe déjà avec cette adresse.';
}
