@extends($layout)

@section($layout_section)
    <div class="flex flex-col gap-6" data-workflows-instance="{{ $instance['id'] }}">
        @component('workflows::partials.header', ['title' => $instance['subject']['label'] ?? $instance['title'], 'description' => $workflow['name'], 'back_url' => $urls['back'], 'back_label' => $strings['back']])
            @slot('actions')
                @include('workflows::partials.instance-status', ['instance' => $instance])
            @endslot
        @endcomponent

        @include('workflows::partials.status')

        <dl class="grid gap-3 rounded-md border p-4 text-sm md:grid-cols-4">
            <div><dt class="text-muted-foreground">{{ $strings['step'] }}</dt><dd class="font-medium" dir="auto">{{ $instance['step_name'] ?? '—' }}</dd></div>
            <div><dt class="text-muted-foreground">{{ $strings['started_by'] }}</dt><dd dir="auto">{{ $instance['starter'] ?? '—' }}</dd></div>
            <div><dt class="text-muted-foreground">{{ $strings['started'] }}</dt><dd>{{ $instance['started_at_label'] ?? '—' }}</dd></div>
            @if($instance['status'] === 'active')
                <div><dt class="text-muted-foreground">{{ $strings['assignees'] }}</dt><dd dir="auto">{{ $instance['assignees'] === [] ? '—' : implode(', ', $instance['assignees']) }}</dd></div>
            @else
                <div><dt class="text-muted-foreground">{{ $strings['completed_at'] }}</dt><dd>{{ $instance['completed_at_label'] ?? '—' }}</dd></div>
            @endif
            @if($instance['due_at_label'])
                <div><dt class="text-muted-foreground">{{ $strings['due'] }}</dt><dd @class(['text-destructive font-medium' => $instance['overdue']])>{{ $instance['due_at_label'] }}@if($instance['overdue']) ({{ $strings['overdue'] }})@endif</dd></div>
            @endif
        </dl>

        @if($can['act'])
            <x-forms::card :title="$strings['your_turn']" data-workflows-act>
                <form method="POST" action="{{ $urls['transition'] }}" enctype="multipart/form-data" class="flex flex-col gap-4">
                    @csrf
                    @if($form)
                        {{ $form }}
                    @endif

                    @php($needs_comment = collect($transitions)->contains(fn ($transition) => $transition['comment'] !== 'none'))
                    @if($needs_comment)
                        <x-forms::textarea name="comment" :label="$strings['comment']" rows="3" dir="auto" />
                    @endif

                    <x-forms::errors name="transition" />

                    <div class="flex flex-wrap gap-2">
                        @foreach($transitions as $transition)
                            <x-forms::button
                                type="submit"
                                name="transition"
                                :value="$transition['key']"
                                :color="$transition['color']"
                                :formnovalidate="$transition['skips_form']"
                                :data-comment="$transition['comment']"
                                dir="auto"
                            >{{ $transition['label'] }}</x-forms::button>
                        @endforeach
                    </div>
                </form>
            </x-forms::card>
        @endif

        @foreach($entries as $entry)
            <x-forms::card :title="$entry['name']" data-workflows-entry="{{ $entry['step'] }}">
                {{ $entry['display'] }}
            </x-forms::card>
        @endforeach

        @if($data !== [])
            <x-forms::card :title="$strings['data']">
                <dl class="grid gap-2 text-sm md:grid-cols-2">
                    @foreach($data as $item)
                        <div><dt class="text-muted-foreground">{{ $item['key'] }}</dt><dd dir="auto">{{ $item['value'] }}</dd></div>
                    @endforeach
                </dl>
            </x-forms::card>
        @endif

        <section aria-labelledby="workflows-history" class="flex flex-col gap-3">
            <h2 id="workflows-history" class="text-lg font-semibold">{{ $strings['history'] }}</h2>
            <ol class="flex flex-col gap-2 border-s ps-4">
                @foreach($history as $entry)
                    @continue(in_array($entry['event'], ['entered'], true))
                    <li class="flex flex-col" data-history-event="{{ $entry['event'] }}">
                        <span class="text-sm"><span class="font-medium" dir="auto">{{ $entry['label'] }}</span> · <span dir="auto">{{ $entry['actor'] ?? $strings['system'] }}</span></span>
                        <span class="text-xs text-muted-foreground">{{ $entry['at_label'] }}</span>
                        @if($entry['comment'])
                            <blockquote class="mt-1 border-s-2 ps-2 text-sm" dir="auto">{{ $entry['comment'] }}</blockquote>
                        @endif
                        @if($entry['error'])
                            <span class="text-xs text-destructive" dir="ltr">{{ $entry['error'] }}</span>
                        @endif
                    </li>
                @endforeach
            </ol>
        </section>

        @if($can['cancel'])
            <details class="rounded-md border p-3">
                <summary class="cursor-pointer text-sm font-medium">{{ $strings['cancel_instance'] }}</summary>
                <form method="POST" action="{{ $urls['cancel'] }}" class="mt-3 flex flex-col gap-3" data-confirm="{{ $strings['confirm_cancel'] }}">
                    @csrf
                    <x-forms::textarea name="reason" :label="$strings['cancel_reason']" rows="2" dir="auto" />
                    <div>
                        <x-forms::button type="submit" color="danger">{{ $strings['cancel_instance'] }}</x-forms::button>
                    </div>
                </form>
            </details>
        @endif
    </div>
@endsection
