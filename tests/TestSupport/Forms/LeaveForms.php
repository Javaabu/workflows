<?php

namespace Javaabu\Workflows\Tests\TestSupport\Forms;

use Javaabu\FormBuilder\Definitions\Field;
use Javaabu\FormBuilder\Definitions\Section;
use Javaabu\FormBuilder\Facades\FormBuilder;
use Javaabu\FormBuilder\Models\Form;

/**
 * The forms of the leave request workflow: the request, and the manager's review
 */
class LeaveForms
{
    public static function request(): Form
    {
        return FormBuilder::define('leave-request')
            ->name('Leave request')
            ->sections([
                Section::make('main')->fields([
                    Field::select('leave_type')->label('Leave type')->required()->options(['annual' => 'Annual', 'sick' => 'Sick']),
                    Field::number('days')->label('Days')->required(),
                    Field::textarea('reason')->label('Reason')->thaana(),
                ]),
            ])
            ->sync();
    }

    public static function review(): Form
    {
        return FormBuilder::define('leave-review')
            ->name('Leave review')
            ->sections([
                Section::make('main')->fields([
                    Field::number('approved_days')->label('Approved days')->required(),
                    Field::textarea('note')->label('Note')->thaana(),
                ]),
            ])
            ->sync();
    }
}
