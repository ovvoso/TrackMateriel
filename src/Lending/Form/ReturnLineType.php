<?php

declare(strict_types=1);

namespace App\Lending\Form;

use App\Inventory\Enum\AssetCondition;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * @extends AbstractType<ReturnLineInput>
 */
final class ReturnLineType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('returned', CheckboxType::class, ['label' => 'Rendu', 'required' => false])
            ->add('condition', EnumType::class, [
                'label' => 'État au retour',
                'class' => AssetCondition::class,
                'choice_label' => static fn (AssetCondition $condition) => $condition->label(),
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => ReturnLineInput::class,
            'empty_data' => null,
            'translation_domain' => false,
        ]);
    }
}
