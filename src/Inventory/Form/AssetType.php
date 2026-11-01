<?php

declare(strict_types=1);

namespace App\Inventory\Form;

use App\Inventory\Entity\Category;
use App\Inventory\Entity\Location;
use App\Inventory\Enum\AssetCondition;
use App\Inventory\Repository\CategoryRepository;
use App\Inventory\Repository\LocationRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\MoneyType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Intl\Currencies;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * @extends AbstractType<AssetInput>
 */
final class AssetType extends AbstractType
{
    public function __construct(
        private readonly LocationRepository $locations,
        #[Autowire('%app.currency%')]
        private readonly string $currency,
    ) {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, [
                'label' => 'Désignation',
                'attr' => ['placeholder' => 'Ex. Ordinateur portable salle informatique'],
            ])
            ->add('category', EntityType::class, [
                'label' => 'Catégorie',
                'class' => Category::class,
                'choice_label' => 'name',
                'placeholder' => 'Choisir une catégorie',
                'query_builder' => static fn (CategoryRepository $repository) => $repository->createQueryBuilder('c')->orderBy('c.name'),
            ])
            ->add('brand', TextType::class, ['label' => 'Marque', 'required' => false])
            ->add('model', TextType::class, ['label' => 'Modèle', 'required' => false])
            ->add('serialNumber', TextType::class, [
                'label' => 'Numéro de série',
                'required' => false,
                'attr' => ['class' => 'mono', 'autocomplete' => 'off', 'spellcheck' => 'false'],
            ])
            ->add('condition', EnumType::class, [
                'label' => 'État physique',
                'class' => AssetCondition::class,
                'choice_label' => static fn (AssetCondition $condition) => $condition->label(),
            ])
            ->add('location', EntityType::class, [
                'label' => 'Emplacement',
                'class' => Location::class,
                'choices' => $this->locations->findAllOrderedByPath(),
                'choice_label' => 'path',
                'placeholder' => 'Non rangé',
                'required' => false,
            ])
            ->add('purchasedAt', DateType::class, [
                'label' => 'Date d’achat',
                'required' => false,
                'input' => 'datetime_immutable',
            ])
            ->add('purchasePrice', MoneyType::class, [
                'label' => 'Prix d’achat',
                'required' => false,
                'currency' => $this->currency,
                'scale' => Currencies::getFractionDigits($this->currency),
                'input' => 'string',
            ])
            ->add('warrantyEndsAt', DateType::class, [
                'label' => 'Fin de garantie',
                'required' => false,
                'input' => 'datetime_immutable',
            ])
            ->add('supplier', TextType::class, ['label' => 'Fournisseur', 'required' => false])
            ->add('notes', TextareaType::class, [
                'label' => 'Notes',
                'required' => false,
                'attr' => ['rows' => 4],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => AssetInput::class, 'translation_domain' => false]);
    }
}
