<?php

declare(strict_types=1);

namespace App\Identity\Form;

use App\Identity\Enum\Role;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * @extends AbstractType<InviteInput>
 */
final class InviteType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('firstName', TextType::class, ['label' => 'Prénom', 'attr' => ['autocomplete' => 'off']])
            ->add('lastName', TextType::class, ['label' => 'Nom', 'attr' => ['autocomplete' => 'off']])
            ->add('email', EmailType::class, [
                'label' => 'Adresse e-mail',
                'help' => 'L’invitation y est envoyée ; le lien reste valable 7 jours.',
                'attr' => ['autocomplete' => 'off', 'placeholder' => 'prenom.nom@etablissement.fr'],
            ])
            ->add('role', EnumType::class, [
                'label' => 'Profil',
                'class' => Role::class,
                'choice_label' => static fn (Role $role) => $role->label(),
                'help' => 'Usager : consulte le parc et ses emprunts. Technicien : gère la maintenance. Gestionnaire : gère le parc et les prêts. Administrateur : gère aussi les comptes.',
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => InviteInput::class, 'translation_domain' => false]);
    }
}
