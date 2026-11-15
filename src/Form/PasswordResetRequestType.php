<?php

declare(strict_types=1);

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * @extends AbstractType<array{email: string}>
 */
class PasswordResetRequestType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('email', EmailType::class, [
            'label' => 'password_reset.request.email',
            'attr' => [
                'autocomplete' => 'username',
                'autofocus' => true,
                'placeholder' => 'login.email_placeholder',
                'class' => 'form-control-lg',
            ],
            'constraints' => [
                new Assert\NotBlank(message: 'user.error.email_required'),
                new Assert\Email(message: 'user.error.email_invalid'),
                new Assert\Length(max: 180),
            ],
        ]);
    }

    public function getBlockPrefix(): string
    {
        return 'password_reset_request';
    }
}
