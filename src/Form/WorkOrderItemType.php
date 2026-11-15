<?php

namespace App\Form;

use App\Entity\Part\Part;
use App\Entity\Workshop\WorkOrderItem;
use App\Enum\WorkOrderItemType as ItemType;
use App\Security\Voter\CostVoter;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\CallbackTransformer;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * @extends AbstractType<WorkOrderItem>
 */
class WorkOrderItemType extends AbstractType
{
    public function __construct(
        private readonly Security $security,
    ) {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('type', HiddenType::class)
            ->add('name', TextType::class, [
                'label' => 'work_order_item.field.name',
                'constraints' => [new Assert\NotBlank(), new Assert\Length(max: 255)],
                'attr' => ['placeholder' => 'work_order_item.field.name'],
            ])
            // Required numeric fields coerce an empty submission to '0' so
            // the non-nullable WorkOrderItem::setQuantity()/setUnitPrice()
            // setters never receive null (which would TypeError BEFORE
            // validation gets a chance to run). The Positive / PositiveOrZero
            // constraints then surface a clean validation error.
            ->add('quantity', NumberType::class, [
                'label' => 'work_order_item.field.quantity',
                'scale' => 2,
                'html5' => true,
                'empty_data' => '0',
                'attr' => ['step' => '0.01', 'min' => '0.01'],
                'constraints' => [new Assert\NotBlank(), new Assert\Positive()],
            ])
            ->add('unitPrice', NumberType::class, [
                'label' => 'work_order_item.field.unit_price',
                'scale' => 2,
                'html5' => true,
                'empty_data' => '0',
                'attr' => ['step' => '0.01', 'min' => '0'],
                'constraints' => [new Assert\NotBlank(), new Assert\PositiveOrZero()],
            ])
            ->add('discountAmount', NumberType::class, [
                'label' => 'work_order_item.field.discount',
                'scale' => 2,
                'html5' => true,
                'required' => false,
                'empty_data' => '0',
                'attr' => ['step' => '0.01', 'min' => '0'],
            ])
            ->add('taxRate', NumberType::class, [
                'label' => 'work_order_item.field.tax_rate',
                'scale' => 2,
                'html5' => true,
                'required' => false,
                'empty_data' => '0',
                'attr' => ['step' => '0.01', 'min' => '0', 'max' => '100'],
            ])
            ->add('sortOrder', HiddenType::class);

        $builder->get('type')->addModelTransformer(new CallbackTransformer(
            fn (?ItemType $value): string => $value->value ?? '',
            fn (?string $value): ?ItemType => $value ? ItemType::from($value) : null,
        ));

        if ($options['show_part_field']) {
            $builder
                ->add('part', EntityType::class, [
                    'class' => Part::class,
                    'choice_label' => fn (Part $p): string => $p->getName() . ($p->getSku() ? ' (' . $p->getSku() . ')' : ''),
                    'label' => 'work_order_item.field.part',
                    'required' => false,
                    'placeholder' => 'work_order_item.field.part_placeholder',
                    'autocomplete' => true,
                ]);

            // Without the field an item keeps the cost it has (or goes on
            // using the catalog cost of its part).
            if ($this->security->isGranted(CostVoter::VIEW_COSTS)) {
                $builder->add('costPrice', NumberType::class, [
                    'label' => 'work_order_item.field.cost_price',
                    'scale' => 2,
                    'html5' => true,
                    'required' => false,
                    'attr' => ['step' => '0.01', 'min' => '0'],
                    'constraints' => [new Assert\PositiveOrZero()],
                    'help' => 'work_order_item.help.cost_price',
                ]);
            }
        }
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => WorkOrderItem::class,
            'show_part_field' => false,
        ]);
    }
}
