@extends($layout)

@section($layout_section)
    <div class="flex flex-col gap-6" data-workflows-tasks>
        @include('workflows::partials.header', ['title' => $strings['tasks'], 'description' => $strings['tasks_description']])
        @include('workflows::partials.status')

        <nav class="flex gap-2 border-b" aria-label="{{ $strings['tasks'] }}">
            <a href="{{ $urls['assigned'] }}" @class(['border-b-2 px-3 py-2 text-sm', 'border-primary font-medium' => $view === 'assigned', 'border-transparent text-muted-foreground' => $view !== 'assigned']) @if($view === 'assigned') aria-current="page" @endif>{{ $strings['assigned_to_me'] }}</a>
            <a href="{{ $urls['started'] }}" @class(['border-b-2 px-3 py-2 text-sm', 'border-primary font-medium' => $view === 'started', 'border-transparent text-muted-foreground' => $view !== 'started']) @if($view === 'started') aria-current="page" @endif>{{ $strings['started_by_me'] }}</a>
        </nav>

        @if($items === [])
            <x-forms::alert type="info">{{ $view === 'started' ? $strings['no_started'] : $strings['no_tasks'] }}</x-forms::alert>
        @else
            @include('workflows::partials.instances-table', ['items' => $items])
            @include('workflows::partials.pagination')
        @endif

        @if($startable !== [])
            <section aria-labelledby="workflows-start" class="flex flex-col gap-3">
                <h2 id="workflows-start" class="text-lg font-semibold">{{ $strings['start_something'] }}</h2>
                <ul class="grid gap-3 md:grid-cols-3">
                    @foreach($startable as $workflow)
                        <li class="flex flex-col gap-2 rounded-md border p-3">
                            <span class="font-medium" dir="auto">{{ $workflow['name'] }}</span>
                            @if($workflow['description'])
                                <span class="text-sm text-muted-foreground" dir="auto">{{ $workflow['description'] }}</span>
                            @endif
                            <div><x-forms::link-button :url="$workflow['url']" size="sm">{{ $strings['start'] }}</x-forms::link-button></div>
                        </li>
                    @endforeach
                </ul>
            </section>
        @endif
    </div>
@endsection
