<?php

declare(strict_types=1);

namespace App\Form\Attachment;

use App\Enum\AttachmentCategory;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Constraints\File;

class AttachmentUploadType extends AbstractType
{
    /**
     * Strict allow-list. SVG is intentionally excluded (active content),
     * as are HTML, PHP and generic binary files.
     *
     * @var list<string>
     */
    public const ALLOWED_MIME_TYPES = [
        'application/pdf',
        'image/jpeg',
        'image/png',
        'image/webp',
        'video/mp4',
    ];

    public const MAX_FILE_SIZE = '10M';

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('file', FileType::class, [
                'label' => 'attachment.field.file',
                'required' => true,
                'constraints' => [
                    new Assert\NotNull(message: 'attachment.error.file_required'),
                    new File(
                        maxSize: self::MAX_FILE_SIZE,
                        mimeTypes: self::ALLOWED_MIME_TYPES,
                        maxSizeMessage: 'attachment.error.too_large',
                        mimeTypesMessage: 'attachment.error.mime_type',
                    ),
                ],
            ])
            ->add('category', EnumType::class, [
                'class' => AttachmentCategory::class,
                'label' => 'attachment.field.category',
                'choice_label' => fn (AttachmentCategory $c): string => 'attachment.category.' . $c->value,
                'constraints' => [new Assert\NotNull()],
            ])
            ->add('description', TextareaType::class, [
                'label' => 'attachment.field.description',
                'required' => false,
                'attr' => ['rows' => 2, 'maxlength' => 500],
                'constraints' => [
                    new Assert\Length(max: 500, maxMessage: 'attachment.error.description_too_long'),
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => AttachmentUploadData::class,
        ]);
    }
}
