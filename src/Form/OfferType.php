<?php

namespace App\Form;

use App\Entity\Car\Car;
use App\Entity\Client\Client;
use App\Entity\Sales\Offer;
use App\Enum\OfferStatus;
use App\Service\Formatting\CarLabelFormatter;
use App\Validator\StorableTotals;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * @extends AbstractType<Offer>
 */
class OfferType extends AbstractType
{
    public function __construct(
        private readonly CarLabelFormatter $carLabelFormatter,
    ) {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('client', EntityType::class, [
                'class' => Client::class,
                'choice_label' => 'name',
                'label' => 'offer.field.client',
                'placeholder' => 'offer.field.client_placeholder',
                'constraints' => [new Assert\NotNull()],
                'autocomplete' => true,
            ])
            ->add('car', EntityType::class, [
                'class' => Car::class,
                'choice_label' => fn (Car $car): string => $this->carLabelFormatter->label($car),
                'label' => 'offer.field.car',
                'placeholder' => 'offer.field.car_placeholder',
                'constraints' => [new Assert\NotNull()],
                'autocomplete' => true,
            ])
            ->add('status', EnumType::class, [
                'class' => OfferStatus::class,
                'label' => 'offer.field.status',
                'choice_label' => fn (OfferStatus $status): string => 'offer.status.' . $status->value,
                // "Converted" is set by OfferToWorkOrderConverter only (see
                // OfferStatusUpdater); it must not be selectable by hand.
                'choice_filter' => static fn (?OfferStatus $status): bool => $status !== OfferStatus::Converted,
            ])
            ->add('title', TextType::class, [
                'label' => 'offer.field.title',
                'required' => false,
                'constraints' => [new Assert\Length(max: 255)],
            ])
            ->add('notes', TextareaType::class, [
                'label' => 'offer.field.notes',
                'required' => false,
                'attr' => ['rows' => 3],
            ])
            ->add('validUntil', DateTimeType::class, [
                'label' => 'offer.field.valid_until',
                'required' => false,
                'widget' => 'single_text',
                'input' => 'datetime_immutable',
                'html5' => false,
                'format' => 'yyyy-MM-dd HH:mm',
                'attr' => [
                    'autocomplete' => 'off',
                    'placeholder' => 'YYYY-MM-DD HH:MM',
                    'data-controller' => 'datetime-field',
                ],
            ]);

        if ($options['include_items']) {
            $builder->add('items', CollectionType::class, [
                'entry_type' => OfferItemFormType::class,
                'entry_options' => [
                    'label' => false,
                    'show_part_field' => true,
                ],
                'allow_add' => true,
                'allow_delete' => true,
                'by_reference' => false,
                'label' => false,
                'prototype' => true,
                'prototype_name' => '__item__',
            ]);

            // Drop submitted item rows that are either typeless or look
            // entirely empty (no name AND no quantity AND no unit price).
            // With allow_delete=true these become silent no-ops, which is
            // the right outcome when a user clicks "Add" but never fills
            // anything in. Partially filled rows (e.g. name typed but
            // quantity left blank) are *kept* so validation fires for them.
            $builder->addEventListener(FormEvents::PRE_SUBMIT, static function (FormEvent $event): void {
                $data = $event->getData();
                if (!is_array($data) || !isset($data['items']) || !is_array($data['items'])) {
                    return;
                }
                foreach ($data['items'] as $key => $entry) {
                    if (!is_array($entry)) {
                        unset($data['items'][$key]);
                        continue;
                    }
                    if (empty($entry['type'])) {
                        unset($data['items'][$key]);
                        continue;
                    }
                    $name = trim((string) ($entry['name'] ?? ''));
                    $quantity = trim((string) ($entry['quantity'] ?? ''));
                    $unitPrice = trim((string) ($entry['unitPrice'] ?? ''));
                    if ($name === '' && $quantity === '' && $unitPrice === '') {
                        unset($data['items'][$key]);
                    }
                }
                $event->setData($data);
            });
        }
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Offer::class,
            'include_items' => false,
            'constraints' => [new StorableTotals()],
        ]);
    }
}
