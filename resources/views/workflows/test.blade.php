@extends($layout)

@section($layout_section)
    @manifestRequire('workflows::builder')

    @php($path = array_column($simulation['path'] ?? [], 'step'))

    <div class="flex flex-col gap-6" data-workflows-tester>
        @include('workflows::partials.header', ['title' => $strings['tester'], 'description' => $strings['tester_help'], 'back_url' => $urls['back'], 'back_label' => $workflow['name']])

        <form method="POST" action="{{ $urls['run'] }}" class="flex flex-col gap-4">
            @csrf
            <div class="grid gap-4 md:grid-cols-2">
                <x-forms::textarea name="data" :label="$strings['sample_data']" :help="$strings['sample_data_help']" rows="5" dir="ltr" :value="$input['data']" />
                <x-forms::textarea name="subject" :label="$strings['sample_subject']" :help="$strings['sample_subject_help']" rows="5" dir="ltr" :value="$input['subject']" />
            </div>

            @if($tasks !== [])
                <fieldset class="flex flex-col gap-3 rounded-md border p-3">
                    <legend class="px-1 font-medium">{{ $strings['choices'] }}</legend>
                    <p class="text-sm text-muted-foreground">{{ $strings['choices_help'] }}</p>
                    <div class="grid gap-3 md:grid-cols-2">
                        @foreach($tasks as $task)
                            <div class="flex flex-col gap-1">
                                <label for="choice-{{ $task['key'] }}" class="text-sm font-medium" dir="auto">{{ $task['name'] }}</label>
                                <select id="choice-{{ $task['key'] }}" name="choices[{{ $task['key'] }}]" class="h-9 rounded-md border bg-background px-2">
                                    <option value="">{{ $strings['stop_here'] }}</option>
                                    @foreach($task['transitions'] as $transition)
                                        <option value="{{ $transition['key'] }}" @selected(($input['choices'][$task['key']] ?? null) === $transition['key'])>{{ $transition['label'] }}</option>
                                    @endforeach
                                </select>
                            </div>
                        @endforeach
                    </div>
                </fieldset>
            @endif

            @if($has_draft)
                <label class="flex items-center gap-2 text-sm">
                    <input type="hidden" name="draft" value="0">
                    <input type="checkbox" name="draft" value="1" @checked($input['draft'])>
                    {{ $strings['test_draft'] }}
                </label>
            @endif

            <div>
                <x-forms::button type="submit" start-icon="play">{{ $strings['run'] }}</x-forms::button>
            </div>
        </form>

        @if($simulation)
            <section class="flex flex-col gap-3" aria-labelledby="workflows-result" data-simulation-status="{{ $simulation['status'] }}">
                <h2 id="workflows-result" class="text-lg font-semibold">{{ $strings['result'] }}</h2>
                <x-forms::alert :type="$simulation['status'] === 'completed' ? 'success' : ($simulation['status'] === 'waiting' ? 'info' : 'warning')" role="status">
                    <strong>{{ $strings['status_' . $simulation['status']] }}</strong>
                    @if($simulation['message']) — {{ $simulation['message'] }} @endif
                </x-forms::alert>

                <ol class="flex flex-col gap-2" aria-label="{{ $strings['path'] }}">
                    @foreach($simulation['path'] as $index => $step)
                        <li class="rounded-md border p-3" data-path-step="{{ $step['step'] }}">
                            <div class="flex flex-wrap items-center gap-2">
                                <span class="text-muted-foreground">{{ $index + 1 }}.</span>
                                <span class="font-medium" dir="auto">{{ $step['name'] }}</span>
                                <span class="text-xs text-muted-foreground">{{ \Javaabu\Workflows\Support\Strings::stepType($step['type']) }}</span>
                            </div>
                            @foreach(array_merge($step['actions'] ?? [], $step['transition_actions'] ?? []) as $action)
                                <p class="ms-6 text-sm"><span class="text-muted-foreground">{{ $strings['would_run'] }}:</span> <span dir="auto">{{ $action['description'] }}</span></p>
                            @endforeach
                            @isset($step['transition'])
                                <p class="ms-6 text-sm">
                                    <span class="text-muted-foreground">{{ $strings['took'] }}:</span>
                                    <span dir="auto">{{ $step['transition_label'] ?? $step['transition'] }}</span>
                                    <span class="text-muted-foreground">({{ $strings['reason_' . $step['reason']] }})</span>
                                </p>
                            @endisset
                            @isset($step['options'])
                                <ul class="ms-6 list-disc text-sm">
                                    @foreach($step['options'] as $option)
                                        <li @class(['text-muted-foreground line-through' => ! $option['available']]) dir="auto">{{ $option['label'] ?? $option['key'] }}</li>
                                    @endforeach
                                </ul>
                            @endisset
                        </li>
                    @endforeach
                </ol>

                @if($simulation['data'] !== [])
                    <details>
                        <summary class="cursor-pointer text-sm font-medium">{{ $strings['final_data'] }}</summary>
                        <pre class="mt-2 overflow-x-auto rounded-md bg-muted p-3 text-xs" dir="ltr">{{ json_encode($simulation['data'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
                    </details>
                @endif
            </section>
        @endif

        @if($definition)
            <div
                data-workflow-graph
                data-props="{{ json_encode(['definition' => $definition, 'catalog' => $catalog, 'highlight' => $path, 'strings' => $strings], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}"
            >
                @include('workflows::partials.graph-list', ['definition' => $definition, 'highlight' => $path])
            </div>
        @endif
    </div>
@endsection
