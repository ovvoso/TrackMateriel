<?php

declare(strict_types=1);

namespace App\Identity\Validator;

use App\Identity\Repository\UserRepository;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

final class AvailableEmailValidator extends ConstraintValidator
{
    public function __construct(private readonly UserRepository $users)
    {
    }

    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$constraint instanceof AvailableEmail) {
            throw new UnexpectedTypeException($constraint, AvailableEmail::class);
        }

        if (!\is_string($value) || '' === trim($value)) {
            return;
        }

        if (null !== $this->users->findOneByEmail($value)) {
            $this->context->buildViolation($constraint->message)->addViolation();
        }
    }
}
