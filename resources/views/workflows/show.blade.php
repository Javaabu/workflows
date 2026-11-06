@extends($layout)

@section($layout_section)
    @manifestRequire('workflows::builder')

    <div class="flex flex-col gap-6" data-workflows-builder-page>
        @component('workflows::partials.header', ['title' => $workflow['name'], 'description' => $workflow['description'], 'back_url' => $urls['index'], 'back_label' => $strings['title']])
            @slot('actions')
                @include('workflows::partials.workflow-status', ['workflow' => $workflow])
                @if($can['update'] && isset($urls['edit']))
                    <x-forms::link-button :url="$urls['edit']" color="outline-secondary" start-icon="settings">{{ $strings['settings'] }}</x-forms::link-button>
                @endif
                <x-forms::link-button :url="$urls['test']" color="outline-secondary" start-icon="play">{{ $strings['test'] }}</x-forms::link-button>
                <x-forms::link-button :url="$urls['versions']" color="outline-secondary">{{ $strings['versions'] }}</x-forms::link-button>
                @if($can['view_instances'])
                    <x-forms::link-button :url="$urls['instances']" color="outline-secondary">{{ $strings['instances'] }}</x-forms::link-button>
                @endif
                <x-forms::link-button :url="$urls['export']" color="outline-secondary" start-icon="download">{{ $strings['export'] }}</x-forms::link-button>
                @if($can['publish'] && $workflow['has_draft'])
                    <form method="POST" action="{{ $urls['publish'] }}" data-confirm="{{ $strings['confirm_publish'] }}">
                        @csrf
                        <x-forms::button type="submit" color="success" start-icon="check">{{ $strings['publish'] }}</x-forms::button>
                    </form>
                @endif
                @if($can['update'] && isset($urls['discard']))
                    <form method="POST" action="{{ $urls['discard'] }}" data-confirm="{{ $strings['confirm_discard'] }}">
                        @csrf
                        @method('DELETE')
                        <x-forms::button type="submit" color="outline-danger">{{ $strings['discard'] }}</x-forms::button>
                    </form>
                @endif
            @endslot
        @endcomponent

        @include('workflows::partials.status')

        @if($workflow['is_locked'])
            <x-forms::alert type="info"><strong>{{ $strings['read_only'] }}</strong> {{ $strings['locked_help'] }}</x-forms::alert>
        @endif

        @if($builder['errors'] !== [])
            <x-forms::alert type="warning" data-workflows-problems>
                <p class="font-medium">{{ $strings['problems'] }}</p>
                <ul class="ms-4 list-disc">
                    @foreach($builder['errors'] as $messages)
                        @foreach($messages as $message)
                            <li>{{ $message }}</li>
                        @endforeach
                    @endforeach
                </ul>
            </x-forms::alert>
        @endif

        @isset($urls['assistant'])
            @include('workflows::partials.assistant', ['url' => $urls['assistant'], 'title' => $strings['assistant_change'], 'help' => $strings['assistant_change_help']])
        @endisset

        <div
            data-workflow-builder
            data-props="{{ json_encode(['workflow' => $workflow, 'builder' => $builder, 'can' => $can, 'urls' => $urls, 'strings' => $strings, 'csrf' => csrf_token()], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}"
        >
            @include('workflows::partials.graph-list', ['definition' => $builder['definition']])
        </div>
    </div>
@endsection
