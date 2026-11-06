@extends($layout)

@section($layout_section)
    <div class="flex flex-col gap-6" data-workflows-instances>
        @include('workflows::partials.header', ['title' => $strings['instances'], 'description' => $record['name'], 'back_url' => $urls['back'], 'back_label' => $strings['back']])

        <form method="GET" action="{{ $urls['index'] }}" class="flex flex-wrap items-end gap-2">
            <div class="flex flex-col gap-1">
                <label for="workflows-status" class="text-sm font-medium">{{ $strings['status'] }}</label>
                <select id="workflows-status" name="status" class="h-9 rounded-md border bg-background px-2">
                    <option value="">{{ $strings['all_statuses'] }}</option>
                    @foreach($statuses as $option)
                        <option value="{{ $option['value'] }}" @selected($filter === $option['value'])>{{ $option['label'] }}</option>
                    @endforeach
                </select>
            </div>
            <x-forms::button type="submit" color="outline-secondary">{{ $strings['filter'] }}</x-forms::button>
        </form>

        @if($instances === [])
            <x-forms::alert type="info">{{ $strings['no_instances'] }}</x-forms::alert>
        @else
            @include('workflows::partials.instances-table', ['items' => $instances, 'show_workflow' => false])
            @include('workflows::partials.pagination')
        @endif
    </div>
@endsection
