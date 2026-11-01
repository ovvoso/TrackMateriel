<?php

declare(strict_types=1);

namespace App\Identity\Form;

use App\Identity\Enum\Role;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * @extends AbstractType<array{firstName: string, lastName: string, role: Role}>
 */
final class UserEditType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('firstName', TextType::class, ['label' => 'Prénom', 'constraints' => [new Assert\NotBlank(), new Assert\Length(max: 80)]])
            ->add('lastName', TextType::class, ['label' => 'Nom', 'constraints' => [new Assert\NotBlank(), new Assert\Length(max: 80)]])
            ->add('role', EnumType::class, [
                'label' => 'Profil',
                'class' => Role::class,
                'choice_label' => static fn (Role $role) => $role->label(),
                'disabled' => $options['lock_role'],
                'help' => $options['lock_role'] ? 'Vous ne pouvez pas modifier votre propre profil.' : null,
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['translation_domain' => false, 'lock_role' => false]);
        $resolver->setAllowedTypes('lock_role', 'bool');
    }
}
