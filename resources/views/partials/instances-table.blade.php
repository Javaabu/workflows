{{-- Instances or tasks in a table: $items, $strings, $show_workflow --}}
<div class="overflow-x-auto rounded-md border">
    <table class="w-full text-sm">
        <thead class="bg-muted/50">
            <tr>
                <th scope="col" class="px-3 py-2 text-start font-medium">{{ $strings['subject'] }}</th>
                @if($show_workflow ?? true)
                    <th scope="col" class="px-3 py-2 text-start font-medium">{{ $strings['workflow'] }}</th>
                @endif
                <th scope="col" class="px-3 py-2 text-start font-medium">{{ $strings['step'] }}</th>
                <th scope="col" class="px-3 py-2 text-start font-medium">{{ $strings['status'] }}</th>
                <th scope="col" class="px-3 py-2 text-start font-medium">{{ $strings['started_by'] }}</th>
                <th scope="col" class="px-3 py-2 text-start font-medium">{{ $strings['due'] }}</th>
                <th scope="col" class="px-3 py-2 text-end font-medium"><span class="sr-only">{{ $strings['actions'] }}</span></th>
            </tr>
        </thead>
        <tbody>
            @foreach($items as $instance)
                <tr class="border-t" data-instance-id="{{ $instance['id'] }}">
                    <td class="px-3 py-2" dir="auto">{{ $instance['subject']['label'] ?? $instance['title'] }}</td>
                    @if($show_workflow ?? true)
                        <td class="px-3 py-2" dir="auto">{{ $instance['workflow'] }}</td>
                    @endif
                    <td class="px-3 py-2" dir="auto">{{ $instance['step_name'] ?? '—' }}</td>
                    <td class="px-3 py-2">@include('workflows::partials.instance-status', ['instance' => $instance])</td>
                    <td class="px-3 py-2" dir="auto">{{ $instance['starter'] ?? '—' }}</td>
                    <td class="px-3 py-2">
                        @if($instance['due_at_label'])
                            <span @class(['text-destructive font-medium' => $instance['overdue']])>{{ $instance['due_at_label'] }}</span>
                            @if($instance['overdue'])
                                <span class="sr-only">({{ $strings['overdue'] }})</span>
                            @endif
                        @else
                            —
                        @endif
                    </td>
                    <td class="px-3 py-2 text-end">
                        @if($instance['url'])
                            <x-forms::link-button :url="$instance['url']" color="outline-secondary" size="sm">{{ $strings['open'] }}</x-forms::link-button>
                        @endif
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>
</div>
