<?php

namespace App\Form;

use App\Entity\Part\Part;
use App\Security\Voter\CostVoter;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * @extends AbstractType<Part>
 */
class PartType extends AbstractType
{
    public function __construct(
        private readonly Security $security,
    ) {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, [
                'label' => 'part.field.name',
                'constraints' => [new Assert\NotBlank(), new Assert\Length(max: 255)],
            ])
            ->add('brand', TextType::class, [
                'label' => 'part.field.brand',
                'required' => false,
                'constraints' => [new Assert\Length(max: 100)],
            ])
            ->add('sku', TextType::class, [
                'label' => 'part.field.sku',
                'required' => false,
                'help' => 'part.help.sku_optional',
                'constraints' => [new Assert\Length(max: 100)],
            ])
            ->add('partNumber', TextType::class, [
                'label' => 'part.field.part_number',
                'required' => false,
                'help' => 'part.help.part_number_optional',
                'constraints' => [new Assert\Length(max: 100)],
            ]);

        // Without the field the stored cost is left as it is, and a request
        // that sends one anyway is rejected as a whole.
        if ($this->security->isGranted(CostVoter::VIEW_COSTS)) {
            $builder->add('costPrice', NumberType::class, [
                'label' => 'part.field.cost_price',
                'scale' => 2,
                'html5' => true,
                'required' => false,
                'attr' => ['step' => '0.01', 'min' => '0'],
                'constraints' => [new Assert\PositiveOrZero()],
            ]);
        }

        $builder
            ->add('sellPrice', NumberType::class, [
                'label' => 'part.field.sell_price',
                'scale' => 2,
                'html5' => true,
                'required' => false,
                'attr' => ['step' => '0.01', 'min' => '0'],
                'constraints' => [new Assert\PositiveOrZero()],
            ])
            ->add('notes', TextareaType::class, [
                'label' => 'part.field.notes',
                'required' => false,
                'attr' => ['rows' => 3],
            ])
            ->add('isActive', CheckboxType::class, [
                'label' => 'part.field.is_active',
                'required' => false,
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Part::class,
        ]);
    }
}
