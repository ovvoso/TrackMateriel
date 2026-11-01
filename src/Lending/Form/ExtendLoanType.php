<?php

declare(strict_types=1);

namespace App\Lending\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * @extends AbstractType<array{dueAt: \DateTimeImmutable|null}>
 */
final class ExtendLoanType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $current = $options['current_due'];
        \assert($current instanceof \DateTimeImmutable);

        $builder->add('dueAt', DateType::class, [
            'label' => 'Nouvelle date de retour',
            'input' => 'datetime_immutable',
            'data' => $current->modify('+7 days'),
            'constraints' => [
                new Assert\NotNull(),
                new Assert\GreaterThan(value: $current, message: 'Choisissez une date après le {{ compared_value }}.'),
                new Assert\LessThanOrEqual(value: '+1 year', message: 'Un prêt ne peut pas dépasser un an.'),
            ],
        ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setRequired('current_due');
        $resolver->setAllowedTypes('current_due', \DateTimeImmutable::class);
        $resolver->setDefaults(['translation_domain' => false]);
    }
}
