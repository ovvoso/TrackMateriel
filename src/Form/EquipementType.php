<?php

namespace App\Form;

use App\Entity\Equipement;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class EquipementType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('nom', TextType::class, [
                'label' => 'Nom de l\'équipement',
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'Ex: PC-001, Imprimante Laser...'
                ]
            ])
            ->add('type', ChoiceType::class, [
                'label' => 'Type d\'équipement',
                'choices' => [
                    'Ordinateur' => 'Ordinateur',
                    'Imprimante' => 'Imprimante',
                    'Écran' => 'Écran',
                    'Clavier' => 'Clavier',
                    'Souris' => 'Souris',
                    'Téléphone' => 'Téléphone',
                    'Autre' => 'Autre',
                ],
                'placeholder' => 'Choisir un type',
                'attr' => [
                    'class' => 'form-select'
                ]
            ])
            ->add('etat', ChoiceType::class, [
                'label' => 'État de l\'équipement',
                'choices' => [
                    'Bon' => 'Bon',
                    'Moyen' => 'Moyen',
                    'Mauvais' => 'Mauvais',
                ],
                'placeholder' => 'Choisir un état',
                'attr' => [
                    'class' => 'form-select'
                ]
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Equipement::class,
        ]);
    }
}
