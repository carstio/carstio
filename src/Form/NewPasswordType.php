<?php

declare(strict_types=1);

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\RepeatedType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * A new password, typed twice. The rules are those of the user form
 * ({@see UserType}).
 *
 * @extends AbstractType<array{plainPassword: string}>
 */
class NewPasswordType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('plainPassword', RepeatedType::class, [
            'type' => PasswordType::class,
            'invalid_message' => 'user.error.password_mismatch',
            'options' => [
                'attr' => ['autocomplete' => 'new-password', 'class' => 'form-control-lg'],
            ],
            'first_options' => ['label' => 'password_reset.confirm.password'],
            'second_options' => ['label' => 'password_reset.confirm.password_repeat'],
            'constraints' => [
                new Assert\NotBlank(message: 'user.error.password_required'),
                // 4096 is the longest password Symfony's hashers accept.
                new Assert\Length(min: 8, max: 4096, minMessage: 'user.error.password_too_short'),
            ],
        ]);
    }

    public function getBlockPrefix(): string
    {
        return 'new_password';
    }
}
