<?php

namespace App\Form;

use App\Entity\Workshop\Payment;
use App\Entity\Workshop\WorkOrder;
use App\Enum\PaymentMethod;
use App\Enum\PaymentStatus;
use App\Validator\StorableAmount;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * @extends AbstractType<Payment>
 */
class PaymentType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('workOrder', EntityType::class, [
                'class' => WorkOrder::class,
                'choice_label' => 'number',
                'label' => 'payment.field.work_order',
                'placeholder' => 'payment.field.work_order_placeholder',
                'constraints' => [new Assert\NotNull()],
                'disabled' => $options['work_order_locked'],
                'autocomplete' => true,
            ])
            ->add('amount', NumberType::class, [
                'label' => 'payment.field.amount',
                'scale' => 2,
                'html5' => true,
                'attr' => ['step' => '0.01', 'min' => '0.01'],
                'constraints' => [new Assert\NotBlank(), new Assert\Positive(), new StorableAmount()],
            ])
            ->add('method', EnumType::class, [
                'class' => PaymentMethod::class,
                'label' => 'payment.field.method',
                'placeholder' => 'payment.field.method_placeholder',
                'choice_label' => fn (PaymentMethod $method): string => 'payment.method.' . $method->value,
                'constraints' => [new Assert\NotNull()],
            ])
            ->add('status', EnumType::class, [
                'class' => PaymentStatus::class,
                'label' => 'payment.field.status',
                'choice_label' => fn (PaymentStatus $status): string => 'payment.status.' . $status->value,
            ])
            ->add('paidAt', DateTimeType::class, [
                'label' => 'payment.field.paid_at',
                'widget' => 'single_text',
                'input' => 'datetime_immutable',
                'html5' => false,
                'format' => 'yyyy-MM-dd HH:mm',
                'attr' => [
                    'autocomplete' => 'off',
                    'placeholder' => 'YYYY-MM-DD HH:MM',
                    'data-controller' => 'datetime-field',
                    'data-datetime-field-now-value' => 'true',
                ],
            ])
            ->add('reference', TextType::class, [
                'label' => 'payment.field.reference',
                'required' => false,
                'constraints' => [new Assert\Length(max: 255)],
            ])
            ->add('note', TextareaType::class, [
                'label' => 'payment.field.note',
                'required' => false,
                'attr' => ['rows' => 2],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Payment::class,
            'work_order_locked' => false,
        ]);
    }
}
