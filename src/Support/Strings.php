<?php

namespace Javaabu\Workflows\Support;

/**
 * The strings the pages show, in the current locale. Blade views and React
 * pages get the same ones, so both stacks read the same.
 */
class Strings
{
    /**
     * @return array<string, string>
     */
    public static function common(): array
    {
        return [
            'back' => __('Back'),
            'cancel' => __('Cancel'),
            'edit' => __('Edit'),
            'delete' => __('Delete'),
            'save' => __('Save'),
            'view' => __('View'),
            'actions' => __('Actions'),
            'confirm_delete' => __('Delete this? This can\'t be undone.'),
            'previous_page' => __('Previous'),
            'next_page' => __('Next'),
            'page_of' => __('Page :page of :pages'),
            'none' => __('None'),
        ];
    }

    /**
     * The workflows list, settings, versions pages
     *
     * @return array<string, string>
     */
    public static function workflows(): array
    {
        return [
            ...static::common(),
            'title' => __('Workflows'),
            'description' => __('Steps, people and rules for getting things approved and done.'),
            'create' => __('New workflow'),
            'search' => __('Search workflows'),
            'search_button' => __('Search'),
            'empty' => __('There are no workflows yet.'),
            'no_results' => __('No workflows match your search.'),
            'name' => __('Name'),
            'status' => __('Status'),
            'version' => __('Version'),
            'instances' => __('Instances'),
            'active' => __('Active'),
            'locked' => __('Defined in code'),
            'inactive' => __('Not taking new instances'),
            'open_builder' => __('Open'),
            'versions' => __('Versions'),
            'published_at' => __('Published'),
            'author' => __('By'),
            'draft' => __('Draft'),
            'current' => __('Current'),
            'test' => __('Test'),
            'export' => __('Export'),
            'settings' => __('Settings'),
            'assistant' => __('Build with the assistant'),
            'assistant_help' => __('Describe the workflow: its steps, who does each one and what happens next. The assistant makes a draft for you to review.'),
            'assistant_placeholder' => __('Leave requests go to the manager; longer than 5 days also to HR…'),
            'assistant_submit' => __('Make a draft'),
            'assistant_change' => __('Change with the assistant'),
            'assistant_change_help' => __('Describe what to change. The assistant edits the draft.'),
        ];
    }

    /**
     * The builder page
     *
     * @return array<string, string>
     */
    public static function builder(): array
    {
        return [
            ...static::workflows(),
            'canvas' => __('Workflow diagram'),
            'canvas_help' => __('Tab to a step. Arrow keys move it (with Shift, further). Enter edits it, Delete removes it.'),
            'add_step' => __('Add a step'),
            'add_transition' => __('Add a transition'),
            'transition_to' => __('Go to'),
            'connect' => __('Connect'),
            'connect_help' => __('Drag from a step\'s handle to another step, or add the transition here.'),
            'step' => __('Step'),
            'steps' => __('Steps'),
            'transition' => __('Transition'),
            'transitions' => __('Transitions'),
            'key' => __('Key'),
            'type' => __('Type'),
            'label' => __('Label'),
            'step_name' => __('Name'),
            'step_description' => __('Description'),
            'from' => __('From'),
            'to' => __('To'),
            'assignees' => __('Assigned to'),
            'add_assignee' => __('Add an assignee'),
            'remove' => __('Remove'),
            'form' => __('Form'),
            'form_help' => __('The javaabu/form-builder form that collects this step\'s data.'),
            'no_form' => __('No form'),
            'form_version' => __('Version'),
            'latest_version' => __('The published version'),
            'step_actions' => __('When the step starts'),
            'transition_actions' => __('When it\'s taken'),
            'add_action' => __('Add an action'),
            'conditions' => __('Conditions'),
            'conditions_help' => __('Only when these hold. Fields are the workflow\'s data, or subject.… and starter.…'),
            'match_all' => __('All of them'),
            'match_any' => __('Any of them'),
            'add_condition' => __('Add a condition'),
            'field' => __('Field'),
            'operator' => __('Operator'),
            'value' => __('Value'),
            'values_help' => __('Separate values with commas.'),
            'comment' => __('Comment'),
            'skip_form' => __('Skip the step\'s form'),
            'outcome' => __('Outcome'),
            'color' => __('Colour'),
            'due_in' => __('Due in (hours)'),
            'save_draft' => __('Save draft'),
            'saved' => __('Draft saved.'),
            'saving' => __('Saving…'),
            'unsaved' => __('Unsaved changes'),
            'publish' => __('Publish'),
            'discard' => __('Discard draft'),
            'confirm_discard' => __('Discard the draft? The published version stays.'),
            'confirm_publish' => __('Publish the draft? New instances will start on it.'),
            'problems' => __('Fix these before publishing'),
            'unreachable' => __('Nothing leads to these steps'),
            'read_only' => __('This workflow is read-only here.'),
            'locked_help' => __('It\'s defined in code. Change it there and run the seeder.'),
            'moved' => __(':step moved.'),
            'selected' => __(':item selected.'),
            'deleted' => __(':item deleted.'),
            'added' => __(':item added.'),
            'zoom_in' => __('Zoom in'),
            'zoom_out' => __('Zoom out'),
            'fit' => __('Fit to screen'),
            'list_view' => __('Steps and transitions as a list'),
            'no_selection' => __('Select a step or a transition to edit it.'),
            'empty_canvas' => __('Add a start step, the tasks, and an end step, then connect them.'),
            'new_step' => __('New step'),
            'new_transition' => __('New transition'),
            'settings_key' => __('Name'),
            'settings_value' => __('Value'),
            'add_row' => __('Add'),
            'localized_in' => __(':label (:language)'),
        ];
    }

    /**
     * The tester page
     *
     * @return array<string, string>
     */
    public static function tester(): array
    {
        return [
            ...static::builder(),
            'tester' => __('Test the workflow'),
            'tester_help' => __('See the path a run takes with sample data. Nothing is saved and no actions run.'),
            'sample_data' => __('Sample data'),
            'sample_data_help' => __('What the start form would collect, as JSON: {"days": 7}'),
            'sample_subject' => __('Sample subject'),
            'sample_subject_help' => __('Attributes of the record it\'s about, as JSON: {"amount": 1500}'),
            'choices' => __('At each task'),
            'choices_help' => __('What the assignee chooses. Leave it empty to stop there.'),
            'stop_here' => __('Stop here'),
            'test_draft' => __('Test the draft'),
            'run' => __('Run the test'),
            'result' => __('Result'),
            'path' => __('Path'),
            'would_run' => __('Would run'),
            'took' => __('Then'),
            'reason_conditions' => __('its conditions held'),
            'reason_default' => __('no other way matched'),
            'reason_chosen' => __('chosen'),
            'status_completed' => __('Completed'),
            'status_waiting' => __('Waiting'),
            'status_stuck' => __('Stuck'),
            'status_looped' => __('Loops'),
            'final_data' => __('Data at the end'),
            'invalid_json' => __('This isn\'t valid JSON.'),
        ];
    }

    /**
     * Instances, tasks and starting pages
     *
     * @return array<string, string>
     */
    public static function instances(): array
    {
        return [
            ...static::common(),
            'instances' => __('Instances'),
            'instance' => __('Instance :id'),
            'tasks' => __('My tasks'),
            'tasks_description' => __('What\'s waiting for you.'),
            'started_by_me' => __('Started by me'),
            'assigned_to_me' => __('Assigned to me'),
            'no_tasks' => __('Nothing is waiting for you.'),
            'no_started' => __('You haven\'t started anything.'),
            'no_instances' => __('There are no instances yet.'),
            'workflow' => __('Workflow'),
            'subject' => __('About'),
            'step' => __('Step'),
            'status' => __('Status'),
            'started' => __('Started'),
            'started_by' => __('Started by'),
            'completed_at' => __('Finished'),
            'due' => __('Due'),
            'overdue' => __('Overdue'),
            'outcome' => __('Outcome'),
            'assignees' => __('Assigned to'),
            'open' => __('Open'),
            'history' => __('History'),
            'data' => __('Details'),
            'comment' => __('Comment'),
            'comment_required' => __('Comment (required)'),
            'your_turn' => __('Your turn'),
            'waiting_for' => __('Waiting for :assignees'),
            'cancel_instance' => __('Cancel this'),
            'cancel_reason' => __('Why?'),
            'confirm_cancel' => __('Cancel it? It stops here, and its tasks close.'),
            'start' => __('Start'),
            'start_workflow' => __('Start :workflow'),
            'start_something' => __('Start something'),
            'status_active' => __('In progress'),
            'status_completed' => __('Completed'),
            'status_cancelled' => __('Cancelled'),
            'all_statuses' => __('All'),
            'filter' => __('Filter'),
            'event_started' => __('Started'),
            'event_entered' => __('Reached :step'),
            'event_assigned' => __('Assigned to :assignees'),
            'event_transitioned' => __(':transition'),
            'event_action_ran' => __('Ran :action'),
            'event_action_failed' => __(':action failed'),
            'event_completed' => __('Finished: :outcome'),
            'event_cancelled' => __('Cancelled'),
            'system' => __('Automatically'),
        ];
    }

    /**
     * The status label of an instance
     */
    public static function instanceStatus(string $status): string
    {
        return match ($status) {
            'active' => __('In progress'),
            'completed' => __('Completed'),
            'cancelled' => __('Cancelled'),
            default => $status,
        };
    }

    /**
     * The status label of a workflow
     */
    public static function workflowStatus(string $status): string
    {
        return match ($status) {
            'published' => __('Published'),
            'changed' => __('Unpublished changes'),
            default => __('Draft'),
        };
    }

    /**
     * The name of a step type
     */
    public static function stepType(string $type): string
    {
        return match ($type) {
            'start' => __('Start'),
            'task' => __('Task'),
            'decision' => __('Decision'),
            'action' => __('Automatic action'),
            'end' => __('End'),
            default => $type,
        };
    }
}
