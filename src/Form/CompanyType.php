<?php

namespace App\Form;

use App\Entity\Client\Client;
use App\Entity\Client\Company;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

class CompanyType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('client', EntityType::class, [
                'class' => Client::class,
                'choice_label' => 'name',
                'label' => 'company.field.client',
                'placeholder' => 'company.field.client_placeholder',
                'constraints' => [new Assert\NotNull()],
                'autocomplete' => true,
            ])
            ->add('name', TextType::class, [
                'label' => 'company.field.name',
                'constraints' => [new Assert\NotBlank()],
            ])
            ->add('city', TextType::class, [
                'label' => 'company.field.city',
                'constraints' => [new Assert\NotBlank()],
            ])
            ->add('address', TextType::class, [
                'label' => 'company.field.address',
                'constraints' => [new Assert\NotBlank()],
            ])
            ->add('identifier', TextType::class, [
                'label' => 'company.field.identifier',
                'constraints' => [new Assert\NotBlank()],
            ])
            ->add('vatIdentifier', TextType::class, [
                'label' => 'company.field.vat_identifier',
                'required' => false,
            ])
            ->add('vat', CheckboxType::class, [
                'label' => 'company.field.vat_registered',
                'required' => false,
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Company::class,
        ]);
    }
}
