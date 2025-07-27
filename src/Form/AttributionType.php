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
                'widget' => 'single_text',
                'label' => 'Date d\'attribution',
                'data' => new \DateTimeImmutable(),
            ])
            ->add('equipement', EntityType::class, [
                'class' => Equipement::class,
                'choice_label' => '__toString',
                'label' => 'Équipement',
                'placeholder' => 'Choisir un équipement',
            ])
            ->add('utilisateur', EntityType::class, [
                'class' => User::class,
                'choice_label' => '__toString',
                'label' => 'Utilisateur',
                'placeholder' => 'Choisir un utilisateur',
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Attribution::class,
        ]);
    }
}
