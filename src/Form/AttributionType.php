<?php

namespace App\Form;

use App\Entity\Attribution;
use App\Entity\Equipement;
use App\Entity\User;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class AttributionType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('dateAttribution', DateTimeType::class, [
                'label' => 'Date d\'attribution',
                'widget' => 'single_text',
                'attr' => [
                    'class' => 'form-control'
                ]
            ])
            ->add('equipement', EntityType::class, [
                'class' => Equipement::class,
                'choice_label' => function(Equipement $equipement) {
                    return $equipement->getNom() . ' (' . $equipement->getType() . ')';
                },
                'label' => 'Équipement',
                'placeholder' => 'Choisir un équipement',
                'attr' => [
                    'class' => 'form-select'
                ]
            ])
            ->add('utilisateur', EntityType::class, [
                'class' => User::class,
                'choice_label' => function(User $user) {
                    return $user->getUsername() . ' (' . $user->getEmail() . ')';
                },
                'label' => 'Utilisateur',
                'placeholder' => 'Choisir un utilisateur',
                'attr' => [
                    'class' => 'form-select'
                ]
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Attribution::class,
        ]);
    }
}
