<?php

declare(strict_types=1);

namespace App\Inventory\Validator;

use Symfony\Component\Validator\Constraint;

#[\Attribute(\Attribute::TARGET_CLASS)]
final class UniqueSerialNumber extends Constraint
{
    public string $message = 'Le numéro de série est déjà attribué à {{ code }}.';

    public function getTargets(): string
    {
        return self::CLASS_CONSTRAINT;
    }
}
