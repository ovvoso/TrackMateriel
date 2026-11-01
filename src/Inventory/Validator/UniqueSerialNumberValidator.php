<?php

declare(strict_types=1);

namespace App\Inventory\Validator;

use App\Inventory\Form\AssetInput;
use App\Inventory\Repository\AssetRepository;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

final class UniqueSerialNumberValidator extends ConstraintValidator
{
    public function __construct(private readonly AssetRepository $assets)
    {
    }

    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$constraint instanceof UniqueSerialNumber) {
            throw new UnexpectedTypeException($constraint, UniqueSerialNumber::class);
        }
        if (!$value instanceof AssetInput) {
            throw new UnexpectedTypeException($value, AssetInput::class);
        }

        $serial = AssetInput::normalizeSerial($value->serialNumber);
        if (null === $serial) {
            return;
        }

        $owner = $this->assets->findOneBy(['serialNumber' => $serial]);
        if (null !== $owner && !$owner->getId()->equals($value->assetId)) {
            $this->context->buildViolation($constraint->message)
                ->setParameter('{{ code }}', $owner->getCode())
                ->atPath('serialNumber')
                ->addViolation();
        }
    }
}
