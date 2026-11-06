# Workflows

[![Tests](https://github.com/Javaabu/workflows/actions/workflows/tests.yml/badge.svg?branch=main)](https://github.com/Javaabu/workflows/actions/workflows/tests.yml?query=branch%3Amain)
[![codecov](https://codecov.io/gh/Javaabu/workflows/branch/main/graph/badge.svg)](https://codecov.io/gh/Javaabu/workflows)
[![Latest Version on Packagist](https://img.shields.io/packagist/v/javaabu/workflows.svg)](https://packagist.org/packages/javaabu/workflows)
[![License: MIT](https://img.shields.io/badge/License-MIT-green.svg)](LICENSE.md)

Dynamic workflows for Laravel: leave requests, purchase approvals, applications, anything that goes through steps where people decide what happens next.

- **Definitions** with steps (start, task, decision, action, end), transitions, assignees and conditions, versioned: instances stay on the version they started with.
- **Instances** with tasks, an inbox per user, a full history and events.
- **Fluent definitions** for seeders, idempotent and lockable to code.
- **A visual builder**: drag, connect and edit steps, all by keyboard too, in Inertia React and Blade.
- **Actions**: notify (javaabu/notifications), update the record, call a webhook, set data, or your own.
- **Step data** with [javaabu/form-builder](https://github.com/Javaabu/form-builder) forms.
- **A tester** that shows the path a run takes with sample data, and an optional [laravel/ai](https://github.com/laravel/ai) assistant that designs workflows.
- Permissions through [javaabu/permissions](https://github.com/Javaabu/permissions) for any guard and user model. Optional [javaabu/translatable](https://github.com/Javaabu/translatable). Dhivehi and RTL.

```php
use Javaabu\Workflows\Definitions\Assignee;
use Javaabu\Workflows\Definitions\Step;
use Javaabu\Workflows\Definitions\Transition;
use Javaabu\Workflows\Facades\Workflows;

Workflows::define('leave-request')
    ->name(['en' => 'Leave request', 'dv' => 'ޗުއްޓީގެ އެދުން'])
    ->for(LeaveRequest::class)
    ->steps([
        Step::start()->form('leave-request'),
        Step::task('review')->assignTo(Assignee::subject('manager'))->runs('notify', ['to' => [['type' => 'assignees']], 'title' => 'A leave request to review']),
        Step::end('approved')->runs('update_model', ['attributes' => ['status' => 'approved']]),
        Step::end('rejected'),
    ])
    ->transitions([
        Transition::make('submit')->from('start')->to('review'),
        Transition::make('approve')->from('review')->to('approved')->label('Approve')->color('success'),
        Transition::make('reject')->from('review')->to('rejected')->label('Reject')->color('danger')->requireComment()->skipForm(),
    ])
    ->sync();

// routes/web.php
Route::workflows(['guard' => 'web_admin', 'prefix' => 'admin/workflows', 'name' => 'admin.workflows']);
Route::workflowTasks();   // /tasks

// anywhere
$instance = $leave->startWorkflow('leave-request', by: $request->user(), data: $request);
$instance->transition('approve', by: $manager, comment: 'ރަނގަޅު');
```

## Documentation

The docs are in [`docs/`](docs/intro.md), indexed for assistants in [`llms.txt`](llms.txt).

## Testing

```bash
composer test
pnpm test
```

## Changelog

See [CHANGELOG](CHANGELOG.md).

## Contributing

See [CONTRIBUTING](CONTRIBUTING.md).

## Security

If you discover a security issue, email info@javaabu.com instead of using the issue tracker.

## Credits

- [Javaabu Pvt. Ltd.](https://github.com/javaabu)
- [All Contributors](../../contributors)

## License

The MIT License (MIT). See [License File](LICENSE.md) for more information.
