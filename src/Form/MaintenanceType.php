<?php

namespace App\Form;

use App\Entity\Equipement;
use App\Entity\Maintenance;
use App\Repository\EquipementRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class MaintenanceType extends AbstractType
{
    public function __construct(private Security $security) {}

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('date', DateTimeType::class, [
                'label' => 'Date de maintenance',
                'widget' => 'single_text',
                'attr' => [
                    'class' => 'form-control'
                ]
            ])
            ->add('observation', TextareaType::class, [
                'label' => 'Observation',
                'required' => false,
                'attr' => [
                    'class' => 'form-control',
                    'rows' => 4,
                    'placeholder' => 'Décrivez les actions effectuées, les problèmes rencontrés...'
                ]
            ])
            ->add('equipement', EntityType::class, [
                'class' => Equipement::class,
                'choice_label' => 'nom',
                'label' => 'Équipement',
                'placeholder' => 'Choisir un équipement',
                'attr' => [
                    'class' => 'form-select'
                ],
                'query_builder' => function (EquipementRepository $er) {
                    $qb = $er->createQueryBuilder('e');
                    
                    // Si l'utilisateur n'est pas admin, limiter aux équipements qui lui sont attribués
                    if (!$this->security->isGranted('ROLE_ADMIN')) {
                        $qb->leftJoin('e.attributions', 'a')
                           ->where('a.utilisateur = :user')
                           ->setParameter('user', $this->security->getUser());
                    }
                    
                    return $qb->orderBy('e.nom', 'ASC');
                }
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Maintenance::class,
        ]);
    }
}
