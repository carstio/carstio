<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\User;
use App\Enum\UserRole;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Used for both creating and editing staff accounts.
 *
 * Password handling differs between the two modes:
 * - on create: the field is required (NotBlank + min length)
 * - on edit:   the field is optional; an empty value keeps the existing hash
 *
 * The role is bound to the {@see UserRole} enum so a request can never
 * smuggle in an arbitrary role string.
 *
 * @extends AbstractType<User>
 */
class UserType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $isEdit = (bool) $options['is_edit'];

        $passwordConstraints = [
            new Assert\Length(min: 8, minMessage: 'user.error.password_too_short'),
        ];
        if (!$isEdit) {
            $passwordConstraints[] = new Assert\NotBlank(message: 'user.error.password_required');
        }

        $builder
            ->add('email', EmailType::class, [
                'label' => 'user.field.email',
                'constraints' => [new Assert\Length(max: 180)],
            ])
            ->add('fullName', TextType::class, [
                'label' => 'user.field.full_name',
                'constraints' => [new Assert\Length(max: 255)],
            ])
            ->add('role', EnumType::class, [
                'class' => UserRole::class,
                'label' => 'user.field.role',
                'mapped' => false,
                'data' => $this->resolveCurrentRole($options['data'] ?? null),
                'choice_label' => fn (UserRole $role): string => match ($role) {
                    UserRole::Admin => 'user.role.admin',
                    UserRole::Mechanic => 'user.role.mechanic',
                },
                'placeholder' => false,
                'constraints' => [
                    new Assert\NotNull(),
                    new Assert\Choice(callback: [UserRole::class, 'cases']),
                ],
            ])
            ->add('plainPassword', PasswordType::class, [
                'label' => $isEdit ? 'user.field.new_password' : 'user.field.password',
                'help' => $isEdit ? 'user.help.password_keep' : null,
                'mapped' => false,
                'required' => !$isEdit,
                'attr' => [
                    'autocomplete' => 'new-password',
                    'placeholder' => $isEdit ? '••••••••' : '',
                ],
                'constraints' => $passwordConstraints,
            ])
            ->add('isActive', CheckboxType::class, [
                'label' => 'user.field.active',
                'required' => false,
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => User::class,
            'is_edit' => false,
        ]);
        $resolver->setAllowedTypes('is_edit', 'bool');
    }

    /**
     * Picks ROLE_ADMIN if the user has it, otherwise ROLE_MECHANIC. Falls
     * back to mechanic for new users so the form has a default selection.
     */
    private function resolveCurrentRole(?User $user): UserRole
    {
        if ($user === null) {
            return UserRole::Mechanic;
        }

        return in_array(UserRole::Admin->value, $user->getRoles(), true)
            ? UserRole::Admin
            : UserRole::Mechanic;
    }
}
