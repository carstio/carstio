<?php

namespace App\Form;

use App\Entity\Car\Car;
use App\Entity\Client\Client;
use App\Entity\Workshop\WorkOrder;
use App\Enum\WorkOrderStatus;
use App\Service\Formatting\CarLabelFormatter;
use App\Service\Workshop\WorkOrderMileageManager;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

class WorkOrderType extends AbstractType
{
    public function __construct(
        private readonly CarLabelFormatter $carLabelFormatter,
        private readonly WorkOrderMileageManager $mileageManager,
    ) {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('client', EntityType::class, [
                'class' => Client::class,
                'choice_label' => 'name',
                'label' => 'work_order.field.client',
                'placeholder' => 'work_order.field.client_placeholder',
                'constraints' => [new Assert\NotNull()],
                'autocomplete' => true,
            ])
            ->add('car', EntityType::class, [
                'class' => Car::class,
                'choice_label' => fn (Car $car): string => $this->carLabelFormatter->label($car),
                'label' => 'work_order.field.car',
                'placeholder' => 'work_order.field.car_placeholder',
                'constraints' => [new Assert\NotNull()],
                'autocomplete' => true,
            ])
            ->add('status', EnumType::class, [
                'class' => WorkOrderStatus::class,
                'label' => 'work_order.field.status',
                'choice_label' => fn (WorkOrderStatus $status): string => 'work_order.status.' . $status->value,
            ])
            ->add('source', TextType::class, [
                'label' => 'work_order.field.source',
                'required' => false,
            ])
            ->add('complaint', TextareaType::class, [
                'label' => 'work_order.field.complaint',
                'constraints' => [new Assert\NotBlank()],
                'attr' => ['rows' => 3],
            ])
            ->add('diagnosis', TextareaType::class, [
                'label' => 'work_order.field.diagnosis',
                'required' => false,
                'attr' => ['rows' => 3],
            ])
            ->add('notes', TextareaType::class, [
                'label' => 'work_order.field.notes',
                'required' => false,
                'attr' => ['rows' => 3],
            ])
            ->add('scheduledAt', DateTimeType::class, [
                'label' => 'work_order.field.scheduled_at',
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

        // Odometer reading is optional - workshops do not always record it.
        // The unit is a snapshot owned by WorkOrderMileageManager (not a
        // form field) so historical readings keep their original label
        // when the Regional setting later changes. The placeholder shows
        // whichever unit will be saved at submit time.
        /** @var WorkOrder|null $existing */
        $existing = $options['data'] ?? null;
        $displayUnit = ($existing !== null && $existing->getOdometerUnit() !== null)
            ? $existing->getOdometerUnit()
            : $this->mileageManager->resolveDefaultUnit();

        $builder->add('odometerReading', IntegerType::class, [
            'label' => 'work_order.field.odometer_reading',
            'help' => 'work_order.help.odometer_reading',
            'required' => false,
            'attr' => [
                'min' => 0,
                'step' => 1,
                'inputmode' => 'numeric',
                'placeholder' => $displayUnit->value,
            ],
        ]);

        if ($options['include_items']) {
            $builder->add('items', CollectionType::class, [
                'entry_type' => WorkOrderItemType::class,
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
            'data_class' => WorkOrder::class,
            'include_items' => false,
        ]);
    }
}
