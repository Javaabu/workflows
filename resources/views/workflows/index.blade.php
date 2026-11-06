@extends($layout)

@section($layout_section)
    <div class="flex flex-col gap-6" data-workflows-index>
        @component('workflows::partials.header', ['title' => $strings['title'], 'description' => $strings['description']])
            @slot('actions')
                @if(isset($urls['create']) && $can['create'])
                    <x-forms::link-button :url="$urls['create']" start-icon="plus">{{ $strings['create'] }}</x-forms::link-button>
                @endif
            @endslot
        @endcomponent

        @include('workflows::partials.status')

        <form method="GET" action="{{ $urls['index'] }}" class="flex flex-wrap items-end gap-2" role="search">
            <div class="flex min-w-64 flex-col gap-1">
                <label for="workflows-search" class="text-sm font-medium">{{ $strings['search'] }}</label>
                <input id="workflows-search" type="search" name="search" value="{{ $search }}" dir="auto" class="h-9 rounded-md border bg-background px-3">
            </div>
            <x-forms::button type="submit" color="outline-secondary">{{ $strings['search_button'] }}</x-forms::button>
        </form>

        @isset($urls['assistant'])
            @include('workflows::partials.assistant', ['url' => $urls['assistant'], 'title' => $strings['assistant'], 'help' => $strings['assistant_help']])
        @endisset

        @if($workflows === [])
            <x-forms::alert type="info">{{ $search !== '' ? $strings['no_results'] : $strings['empty'] }}</x-forms::alert>
        @else
            <div class="overflow-x-auto rounded-md border">
                <table class="w-full text-sm">
                    <thead class="bg-muted/50">
                        <tr>
                            <th scope="col" class="px-3 py-2 text-start font-medium">{{ $strings['name'] }}</th>
                            <th scope="col" class="px-3 py-2 text-start font-medium">{{ $strings['status'] }}</th>
                            <th scope="col" class="px-3 py-2 text-start font-medium">{{ $strings['version'] }}</th>
                            <th scope="col" class="px-3 py-2 text-start font-medium">{{ $strings['active'] }}</th>
                            <th scope="col" class="px-3 py-2 text-end font-medium"><span class="sr-only">{{ $strings['actions'] }}</span></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($workflows as $workflow)
                            <tr class="border-t" data-workflow-key="{{ $workflow['key'] }}">
                                <td class="px-3 py-2">
                                    <a href="{{ $workflow['urls']['show'] }}" class="font-medium hover:underline" dir="auto">{{ $workflow['name'] }}</a>
                                    <div class="text-xs text-muted-foreground" dir="ltr">{{ $workflow['key'] }}</div>
                                </td>
                                <td class="px-3 py-2">@include('workflows::partials.workflow-status', ['workflow' => $workflow])</td>
                                <td class="px-3 py-2">{{ $workflow['published_version'] ?? '—' }}</td>
                                <td class="px-3 py-2">
                                    @isset($workflow['urls']['instances'])
                                        <a href="{{ $workflow['urls']['instances'] }}" class="hover:underline">{{ $workflow['active_count'] }}</a>
                                    @else
                                        {{ $workflow['active_count'] }}
                                    @endisset
                                </td>
                                <td class="px-3 py-2 text-end">
                                    <x-forms::link-button :url="$workflow['urls']['show']" color="outline-secondary" size="sm">{{ $strings['open_builder'] }}</x-forms::link-button>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            @include('workflows::partials.pagination')
        @endif
    </div>
@endsection
