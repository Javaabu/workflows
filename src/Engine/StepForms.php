<?php

namespace Javaabu\Workflows\Engine;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Javaabu\FormBuilder\Facades\FormBuilder;
use Javaabu\FormBuilder\Models\FormEntry;
use Javaabu\FormBuilder\Models\FormVersion;
use Javaabu\FormBuilder\Stencil\EntryForm;
use Javaabu\Workflows\Definitions\Step;
use Javaabu\Workflows\Exceptions\WorkflowException;

/**
 * The javaabu/form-builder forms steps collect their data with: which version
 * a step uses, its stencil form, and storing what was submitted
 */
class StepForms
{
    /**
     * The form version the step fills: the pinned one, or the form's published version
     */
    public function version(Step $step): ?FormVersion
    {
        if ($step->getForm() === null) {
            return null;
        }

        $form = FormBuilder::form($step->getForm());

        if ($step->getFormVersion() !== null) {
            return $form->versions()->where('number', $step->getFormVersion())->whereNotNull('published_at')->first()
                ?? throw new WorkflowException("Version {$step->getFormVersion()} of the form \"{$step->getForm()}\" isn't published.");
        }

        return $form->publishedVersion ?? throw new WorkflowException("The form \"{$step->getForm()}\" isn't published.");
    }

    /**
     * The stencil form to render for the step, or null when it has none
     *
     * @param  array<string, mixed>  $values
     */
    public function stencil(Step $step, ?string $action = null, array $values = []): ?EntryForm
    {
        $version = $this->version($step);

        if (! $version) {
            return null;
        }

        $stencil = EntryForm::for($version);

        if ($action) {
            $stencil->action($action)->method('POST');
        }

        if ($values !== []) {
            $stencil->model($values);
        }

        return $stencil;
    }

    /**
     * Validate and store the step's form; throws ValidationException
     *
     * @param  Request|array<string, mixed>  $input
     */
    public function submit(Step $step, Request|array $input, ?Model $submitter, Model $subject): ?FormEntry
    {
        $version = $this->version($step);

        if (! $version) {
            return null;
        }

        return FormBuilder::submit($version, $input, submitter: $submitter, subject: $subject);
    }
}
