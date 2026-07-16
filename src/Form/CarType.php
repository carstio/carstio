<?php

namespace App\Form;

use App\Entity\Car\Car;
use App\Entity\Car\Model;
use App\Entity\Client\Client;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

class CarType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('client', EntityType::class, [
                'class' => Client::class,
                'choice_label' => 'name',
                'label' => 'car.field.client',
                'placeholder' => 'car.field.client_placeholder',
                'constraints' => [new Assert\NotNull()],
                'autocomplete' => true,
            ])
            ->add('model', EntityType::class, [
                'class' => Model::class,
                'choice_label' => fn (Model $model): string => $model->getDisplayName(),
                'group_by' => fn (Model $model): ?string => $model->getBrand()?->getName(),
                'query_builder' => fn (\Doctrine\ORM\EntityRepository $repo) => $repo->createQueryBuilder('m')
                    ->join('m.brand', 'b')
                    ->orderBy('b.name', 'ASC')
                    ->addOrderBy('m.name', 'ASC'),
                'label' => 'car.field.model',
                'placeholder' => 'car.field.model_placeholder',
                'constraints' => [new Assert\NotNull()],
                'autocomplete' => true,
            ])
            ->add('vin', TextType::class, [
                'label' => 'car.field.vin',
                'required' => false,
            ])
            ->add('licensePlate', TextType::class, [
                'label' => 'car.field.license_plate',
                'required' => false,
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Car::class,
        ]);
    }
}
