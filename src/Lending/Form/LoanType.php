<?php

declare(strict_types=1);

namespace App\Lending\Form;

use App\Identity\Entity\User;
use App\Identity\Repository\UserRepository;
use App\Inventory\Entity\Asset;
use App\Inventory\Repository\AssetRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * @extends AbstractType<LoanInput>
 */
final class LoanType extends AbstractType
{
    public function __construct(
        private readonly AssetRepository $assets,
        private readonly UserRepository $users,
    ) {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('borrowerType', ChoiceType::class, [
                'label' => 'Emprunteur',
                'choices' => ['Personnel' => LoanInput::TO_ACCOUNT, 'Élève ou invité' => LoanInput::TO_PERSON],
                'expanded' => true,
                'attr' => ['data-action' => 'change->borrower#toggle'],
            ])
            ->add('account', EntityType::class, [
                'label' => 'Personne',
                'class' => User::class,
                'choices' => $this->users->findActiveOrderedByName(),
                'choice_label' => static fn (User $user) => $user->getLastName().' '.$user->getFirstName().' · '.$user->getRole()->label(),
                'placeholder' => 'Choisir une personne',
                // Obligatoire selon le type d'emprunteur : vérifié par LoanInput
                'required' => true,
                'row_attr' => ['data-borrower-target' => 'account'],
            ])
            ->add('personName', TextType::class, [
                'label' => 'Nom et prénom',
                'required' => true,
                'row_attr' => ['data-borrower-target' => 'person'],
                'attr' => ['placeholder' => 'Ex. Aminata Sow'],
            ])
            ->add('personGroup', TextType::class, [
                'label' => 'Classe ou service',
                'required' => false,
                'row_attr' => ['data-borrower-target' => 'person'],
                'attr' => ['placeholder' => 'Ex. Terminale S1'],
            ])
            ->add('assets', EntityType::class, [
                'label' => 'Matériel',
                'class' => Asset::class,
                'choices' => $this->assets->findAvailable(),
                'choice_label' => 'name',
                'multiple' => true,
                'expanded' => true,
            ])
            ->add('dueAt', DateType::class, [
                'label' => 'Retour prévu le',
                'input' => 'datetime_immutable',
            ])
            ->add('purpose', TextType::class, [
                'label' => 'Motif',
                'required' => false,
                'attr' => ['placeholder' => 'Ex. Cours de SVT, sortie pédagogique, examen'],
            ])
            ->add('notes', TextareaType::class, [
                'label' => 'Remarques',
                'required' => false,
                'attr' => ['rows' => 3],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => LoanInput::class, 'translation_domain' => false]);
    }
}
