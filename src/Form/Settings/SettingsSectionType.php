<?php

namespace App\Form\Settings;

use App\Enum\SettingType;
use App\Settings\SettingDefinition;
use App\Settings\SettingsRegistry;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\UrlType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Single dynamic form built from a section's {@see SettingDefinition}
 * entries. The form data is a flat array of key => value pairs the
 * controller passes straight to {@see \App\Service\Settings\SettingsManager::setMany()}.
 *
 * File upload (logo) and the SMTP password use unmapped fields added
 * outside this form by the controller - they need bespoke handling and
 * shouldn't leak into the generic save path.
 */
class SettingsSectionType extends AbstractType
{
    public function __construct(private readonly SettingsRegistry $registry)
    {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        /** @var string $section */
        $section = $options['section'];

        foreach ($this->registry->bySection($section) as $definition) {
            // FilePath fields (e.g. company logo) and Secret fields are
            // handled by the controller as unmapped extras - see comment
            // above. The form deliberately ignores them here.
            if ($definition->type === SettingType::FilePath || $definition->type === SettingType::Secret) {
                continue;
            }

            $builder->add($definition->key, $this->resolveFormType($definition), $this->resolveFormOptions($definition));
        }
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver
            ->setRequired('section')
            ->setAllowedTypes('section', 'string');
    }

    /**
     * @return class-string
     */
    private function resolveFormType(SettingDefinition $definition): string
    {
        return match ($definition->type) {
            SettingType::Choice => ChoiceType::class,
            SettingType::Email => EmailType::class,
            SettingType::Url => UrlType::class,
            SettingType::Integer => IntegerType::class,
            SettingType::Float => NumberType::class,
            SettingType::Boolean => CheckboxType::class,
            SettingType::Text => TextareaType::class,
            default => TextType::class,
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function resolveFormOptions(SettingDefinition $definition): array
    {
        $options = [
            'label' => $definition->labelKey,
            'required' => $definition->required,
        ];

        if ($definition->helpKey !== null) {
            $options['help'] = $definition->helpKey;
        }

        $attr = [];
        if ($definition->placeholder !== null) {
            $attr['placeholder'] = $definition->placeholder;
        }

        $constraints = $definition->constraints;
        if ($definition->required) {
            $constraints[] = new Assert\NotBlank();
        }

        switch ($definition->type) {
            case SettingType::Choice:
                $options['choices'] = $definition->choices ?? [];
                $options['placeholder'] = $definition->required ? false : null;
                if ($definition->autocomplete) {
                    $options['autocomplete'] = true;
                }
                break;
            case SettingType::Integer:
                $attr['inputmode'] = 'numeric';
                break;
            case SettingType::Float:
                $options['scale'] = 2;
                $options['html5'] = true;
                $attr += ['step' => '0.01', 'inputmode' => 'decimal'];
                break;
            case SettingType::Url:
                $options['default_protocol'] = 'https';
                break;
            case SettingType::Text:
                $attr['rows'] = 3;
                break;
        }

        if ($attr !== []) {
            $options['attr'] = $attr;
        }

        if ($constraints !== []) {
            $options['constraints'] = $constraints;
        }

        return $options;
    }
}
