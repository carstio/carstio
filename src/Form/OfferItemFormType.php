<?php

namespace App\Form;

use App\Entity\Part\Part;
use App\Entity\Sales\OfferItem;
use App\Enum\OfferItemType;
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
 * @extends AbstractType<OfferItem>
 */
class OfferItemFormType extends AbstractType
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
                'label' => 'offer_item.field.name',
                'constraints' => [new Assert\NotBlank(), new Assert\Length(max: 255)],
                'attr' => ['placeholder' => 'offer_item.field.name'],
            ])
            // Required numeric fields coerce an empty submission to '0' so
            // the non-nullable OfferItem::setQuantity()/setUnitPrice()
            // setters never receive null (which would TypeError BEFORE
            // validation gets a chance to run). The Positive / PositiveOrZero
            // constraints then surface a clean validation error.
            ->add('quantity', NumberType::class, [
                'label' => 'offer_item.field.quantity',
                'scale' => 2,
                'html5' => true,
                'empty_data' => '0',
                'attr' => ['step' => '0.01', 'min' => '0.01'],
                'constraints' => [new Assert\NotBlank(), new Assert\Positive()],
            ])
            ->add('unitPrice', NumberType::class, [
                'label' => 'offer_item.field.unit_price',
                'scale' => 2,
                'html5' => true,
                'empty_data' => '0',
                'attr' => ['step' => '0.01', 'min' => '0'],
                'constraints' => [new Assert\NotBlank(), new Assert\PositiveOrZero()],
            ])
            ->add('discountAmount', NumberType::class, [
                'label' => 'offer_item.field.discount',
                'scale' => 2,
                'html5' => true,
                'required' => false,
                'empty_data' => '0',
                'attr' => ['step' => '0.01', 'min' => '0'],
            ])
            ->add('taxRate', NumberType::class, [
                'label' => 'offer_item.field.tax_rate',
                'scale' => 2,
                'html5' => true,
                'required' => false,
                'empty_data' => '0',
                'attr' => ['step' => '0.01', 'min' => '0', 'max' => '100'],
            ])
            ->add('sortOrder', HiddenType::class);

        $builder->get('type')->addModelTransformer(new CallbackTransformer(
            fn (?OfferItemType $value): string => $value->value ?? '',
            fn (?string $value): ?OfferItemType => $value ? OfferItemType::from($value) : null,
        ));

        if ($options['show_part_field']) {
            $builder
                ->add('part', EntityType::class, [
                    'class' => Part::class,
                    'choice_label' => fn (Part $p): string => $p->getName() . ($p->getSku() ? ' (' . $p->getSku() . ')' : ''),
                    'label' => 'offer_item.field.part',
                    'required' => false,
                    'placeholder' => 'offer_item.field.part_placeholder',
                    'autocomplete' => true,
                ]);

            // Without the field an item keeps the cost it has (or goes on
            // using the catalog cost of its part).
            if ($this->security->isGranted(CostVoter::VIEW_COSTS)) {
                $builder->add('costPrice', NumberType::class, [
                    'label' => 'offer_item.field.cost_price',
                    'scale' => 2,
                    'html5' => true,
                    'required' => false,
                    'attr' => ['step' => '0.01', 'min' => '0'],
                    'constraints' => [new Assert\PositiveOrZero()],
                    'help' => 'offer_item.help.cost_price',
                ]);
            }
        }
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => OfferItem::class,
            'show_part_field' => false,
        ]);
    }
}
