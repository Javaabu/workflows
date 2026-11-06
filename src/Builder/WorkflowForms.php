<?php

namespace Javaabu\Workflows\Builder;

use Javaabu\Stencil\Components\Component;
use Javaabu\Stencil\Components\Group;
use Javaabu\Stencil\Components\Section;
use Javaabu\Stencil\Fields\Checkbox;
use Javaabu\Stencil\Fields\Field as StencilField;
use Javaabu\Stencil\Fields\Select;
use Javaabu\Stencil\Fields\Textarea;
use Javaabu\Stencil\Fields\TextInput;
use Javaabu\Stencil\Form;
use Javaabu\Translatable\Contracts\Translatable;
use Javaabu\Workflows\Models\Workflow;
use Javaabu\Workflows\Support\Translations;
use Javaabu\Workflows\WorkflowsManager;

/**
 * The stencil form of a workflow's settings: key, name, description, subject, active
 */
class WorkflowForms
{
    public const string KEY_PATTERN = '/^[a-z0-9][a-z0-9_-]*$/';

    public function __construct(
        protected WorkflowsManager $workflows,
    ) {}

    public function settingsForm(?Workflow $workflow = null): Form
    {
        $locales = Translations::locales();
        $subjects = (array) config('workflows.subjects', []);

        $subject = $subjects === []
            ? TextInput::make('subject_type')->label(__('Subject'))->maxLength(255)->help(__('The model instances are about, as its morph alias or class. Leave it empty for workflows about nothing in particular.'))
            : Select::make('subject_type')->label(__('Subject'))->options($subjects)->placeholder(__('Nothing in particular'))->rules(['nullable', 'in:' . implode(',', array_keys($subjects))]);

        $stencil = Form::make('workflow-settings')
            ->schema([
                Section::make(__('Workflow'))->columns(2)->schema([
                    TextInput::make('key')
                        ->label(__('Key'))
                        ->required()
                        ->maxLength(100)
                        ->rules(['regex:' . self::KEY_PATTERN])
                        ->rules([function (string $attribute, mixed $value, $fail) use ($workflow) {
                            $exists = $this->workflows->model('workflow')::query()
                                ->withTrashed()
                                ->where('key', $value)
                                ->when($workflow, fn ($query) => $query->whereKeyNot($workflow->getKey()))
                                ->exists();

                            if ($exists) {
                                $fail(__('Another workflow already has this key.'));
                            }
                        }])
                        ->disabled((bool) $workflow?->instances()->exists())
                        ->help(__('Lowercase letters, numbers, dashes and underscores. Code refers to the workflow by it.')),
                    $subject,
                    $this->localized('name', __('Name'), $locales, required: true),
                    $this->localized('description', __('Description'), $locales, 'textarea'),
                    Checkbox::make('is_active')->label(__('Taking new instances'))->default(true),
                ]),
            ]);

        if ($workflow) {
            $stencil->model([
                'key' => $workflow->key,
                'subject_type' => $workflow->subject_type,
                'name' => $this->localizedValue($workflow, 'name', $locales),
                'description' => $this->localizedValue($workflow, 'description', $locales),
                'is_active' => $workflow->is_active,
            ]);
        }

        return $stencil;
    }

    /**
     * An input for text in every locale: one input, or a group with one per locale
     *
     * @param  array<int, string>  $locales
     */
    public function localized(string $name, string $label, array $locales, string $kind = 'text', bool $required = false): Component
    {
        $make = function (string $input_name, string $input_label, ?string $locale, bool $is_required) use ($kind): StencilField {
            $input = $kind === 'textarea'
                ? Textarea::make($input_name)->rows(3)
                : TextInput::make($input_name)->maxLength(255);

            $input->label($input_label)->required($is_required);

            if ($locale !== null && Translations::isRtl($locale)) {
                $input->thaana($locale === 'dv')->extraAttributes(['dir' => 'rtl', 'lang' => $locale]);
            }

            return $input;
        };

        if (count($locales) <= 1) {
            return $make($name, $label, $locales[0] ?? null, $required);
        }

        $inputs = [];

        foreach ($locales as $index => $locale) {
            $inputs[] = $make($locale, "{$label} (" . Translations::localeName($locale) . ')', $locale, $required && $index === 0);
        }

        return Group::make($inputs)->statePath($name)->columns(min(count($inputs), 2))->columnSpanFull();
    }

    /**
     * A model attribute for a localized input: a map of its translations with several locales
     *
     * @param  array<int, string>  $locales
     * @return string|array<string, string|null>|null
     */
    protected function localizedValue(Workflow $workflow, string $attribute, array $locales): string|array|null
    {
        $value = $workflow->getAttribute($attribute);

        if (count($locales) <= 1) {
            return $value;
        }

        $values = [];

        foreach ($locales as $index => $locale) {
            $values[$locale] = $workflow instanceof Translatable && $workflow->isTranslatable($attribute)
                ? $workflow->translate($attribute, $locale, false)
                : ($index === 0 ? $value : null);
        }

        return $values;
    }

    /**
     * Fill the workflow from the validated settings
     *
     * @param  array<string, mixed>  $data
     */
    public function fill(Workflow $workflow, array $data): void
    {
        $default = Translations::defaultLocale();
        $text = fn (mixed $value) => is_array($value) ? ($value[$default] ?? (reset($value) ?: null)) : $value;

        if (isset($data['key']) && ! $workflow->instances()->exists()) {
            $workflow->key = $data['key'];
        }

        $workflow->name = (string) $text($data['name'] ?? $workflow->key);
        $workflow->description = $text($data['description'] ?? null);
        $workflow->subject_type = ($data['subject_type'] ?? null) ?: null;
        $workflow->is_active = (bool) ($data['is_active'] ?? false);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function saveTranslations(Workflow $workflow, array $data): void
    {
        Translations::saveModelText($workflow, ['name' => $data['name'] ?? null, 'description' => $data['description'] ?? null]);
    }
}
