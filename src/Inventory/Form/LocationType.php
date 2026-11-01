<?php

declare(strict_types=1);

namespace App\Inventory\Form;

use App\Inventory\Entity\Location;
use App\Inventory\Enum\LocationKind;
use App\Inventory\Repository\LocationRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * @extends AbstractType<Location>
 */
final class LocationType extends AbstractType
{
    public function __construct(private readonly LocationRepository $locations)
    {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        // Seuls les sites et les bâtiments peuvent contenir d'autres emplacements
        $containers = array_values(array_filter(
            $this->locations->findAllOrderedByPath(),
            static fn (Location $location) => LocationKind::Room !== $location->getKind() && $location !== ($options['data'] ?? null),
        ));

        $builder
            ->add('kind', EnumType::class, [
                'label' => 'Type',
                'class' => LocationKind::class,
                'choice_label' => static fn (LocationKind $kind) => $kind->label(),
                'expanded' => true,
            ])
            ->add('name', TextType::class, [
                'label' => 'Nom',
                'attr' => ['placeholder' => 'Ex. Salle 104, Bâtiment B, Campus principal'],
            ])
            ->add('parent', EntityType::class, [
                'label' => 'Rattaché à',
                'class' => Location::class,
                'choices' => $containers,
                'choice_label' => 'path',
                'placeholder' => 'Aucun (site)',
                'required' => false,
                'help' => 'Une salle se rattache à un bâtiment, un bâtiment à un site.',
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'translation_domain' => false,
            'data_class' => Location::class,
            'empty_data' => static function (FormInterface $form): Location {
                $kind = $form->get('kind')->getData();
                $parent = $form->get('parent')->getData();
                $name = $form->get('name')->getData();

                return new Location(
                    \is_string($name) ? $name : '',
                    $kind instanceof LocationKind ? $kind : LocationKind::Room,
                    $parent instanceof Location ? $parent : null,
                );
            },
        ]);
    }
}
