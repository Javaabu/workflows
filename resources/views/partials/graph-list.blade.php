{{-- The steps and their transitions as a list: the builder's text version, and what shows before its script loads --}}
@php($definition = $definition ?? ['steps' => [], 'transitions' => []])
<ol class="flex flex-col gap-3" data-workflows-graph-list>
    @foreach($definition['steps'] as $step)
        @php($out = array_values(array_filter($definition['transitions'], fn ($transition) => ($transition['from'] ?? null) === $step['key'])))
        <li class="rounded-md border p-3" data-step="{{ $step['key'] }}" @if(in_array($step['key'], $highlight ?? [], true)) data-highlighted @endif>
            <div class="flex flex-wrap items-center gap-2">
                <span class="font-medium" dir="auto">{{ \Javaabu\Workflows\Facades\Workflows::text($step['name'] ?? null) ?? $step['key'] }}</span>
                <span class="text-xs text-muted-foreground">{{ \Javaabu\Workflows\Support\Strings::stepType($step['type'] ?? 'task') }}</span>
                <code class="text-xs text-muted-foreground" dir="ltr">{{ $step['key'] }}</code>
            </div>
            @if($out !== [])
                <ul class="ms-4 mt-2 list-disc text-sm">
                    @foreach($out as $transition)
                        <li>
                            <span dir="auto">{{ \Javaabu\Workflows\Facades\Workflows::text($transition['label'] ?? null) ?? $transition['key'] }}</span>
                            → <span dir="auto">{{ \Javaabu\Workflows\Facades\Workflows::text(collect($definition['steps'])->firstWhere('key', $transition['to'])['name'] ?? null) ?? $transition['to'] }}</span>
                            @if(! empty($transition['conditions']['rules']))
                                <span class="text-muted-foreground">({{ collect($transition['conditions']['rules'])->map(fn ($rule) => trim($rule['field'] . ' ' . $rule['operator'] . ' ' . (is_array($rule['value'] ?? null) ? implode(', ', $rule['value']) : json_encode($rule['value'] ?? null) )))->implode($transition['conditions']['match'] === 'any' ? ' / ' : ', ') }})</span>
                            @endif
                        </li>
                    @endforeach
                </ul>
            @endif
        </li>
    @endforeach
</ol>
