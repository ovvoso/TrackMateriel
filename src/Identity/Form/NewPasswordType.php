<?php

declare(strict_types=1);

namespace App\Identity\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\RepeatedType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Choix d'un nouveau mot de passe : 12 caractères minimum et une robustesse
 * moyenne au moins (estimée sur l'entropie, pas sur des règles de composition).
 *
 * @extends AbstractType<array{password: string|null}>
 */
final class NewPasswordType extends AbstractType
{
    public const int MIN_LENGTH = 12;

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('password', RepeatedType::class, [
            'type' => PasswordType::class,
            'invalid_message' => 'Les deux mots de passe ne correspondent pas.',
            'first_options' => [
                'label' => 'Nouveau mot de passe',
                'help' => \sprintf('Au moins %d caractères. Une phrase de quelques mots est plus sûre et plus facile à retenir.', self::MIN_LENGTH),
                'attr' => ['autocomplete' => 'new-password'],
            ],
            'second_options' => [
                'label' => 'Confirmez le mot de passe',
                'attr' => ['autocomplete' => 'new-password'],
            ],
            'constraints' => [
                new Assert\NotBlank(message: 'Choisissez un mot de passe.'),
                new Assert\Length(min: self::MIN_LENGTH, max: 4096, minMessage: 'Le mot de passe doit contenir au moins {{ limit }} caractères.'),
                new Assert\PasswordStrength(minScore: Assert\PasswordStrength::STRENGTH_MEDIUM, message: 'Ce mot de passe est trop facile à deviner : allongez-le ou variez davantage les caractères.'),
            ],
        ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['translation_domain' => false]);
    }
}
