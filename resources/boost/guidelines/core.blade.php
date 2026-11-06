{{--
    javaabu/workflows guidelines for AI code assistants

    Maintained by: Javaabu (https://javaabu.com)
--}}

@verbatim
## javaabu/workflows

- Approvals and multi-step processes (leave requests, purchases, applications) are javaabu/workflows workflows, not status columns and hand-written controllers: define code-owned ones in a seeder with `Workflows::define('key')->for(Model::class)->steps([Step::start(), Step::task('review')->assignTo(Assignee::role('manager')), Step::end('approved')])->transitions([Transition::make('approve')->from('review')->to('approved')->label('Approve')])->sync()`, and let admins build others on `Route::workflows([...])` pages.
- Start runs with `$model->startWorkflow('key', by: $user, data: $request)` (subjects use `HasWorkflows`) or `Workflows::start()`, and move them with `$instance->transition('approve', by: $user, data: $request, comment: ...)`. Never set `current_step` or `status` yourself; the engine checks assignees, comments, forms and conditions, and records history.
- Collect step data with javaabu/form-builder forms (`->form('form-key')`), react with actions (`->runs('notify'|'update_model'|'webhook'|'set_data', [...])`) or the package's events, and check paths with `Workflows::simulate()`.
- Always activate the `javaabu-workflows` skill when defining or changing workflows, starting or advancing instances, writing actions or assignee types, or setting up the workflow routes, pages and permissions.
@endverbatim
