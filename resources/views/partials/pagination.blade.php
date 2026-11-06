@if($pagination['last_page'] > 1)
    <nav class="flex items-center justify-between gap-2" aria-label="{{ str_replace([':page', ':pages'], [$pagination['current_page'], $pagination['last_page']], $strings['page_of']) }}">
        @if($pagination['current_page'] > 1)
            <x-forms::link-button :url="request()->fullUrlWithQuery(['page' => $pagination['current_page'] - 1])" color="outline-secondary" size="sm">{{ $strings['previous_page'] }}</x-forms::link-button>
        @else
            <span></span>
        @endif
        <span class="text-sm text-muted-foreground">{{ str_replace([':page', ':pages'], [$pagination['current_page'], $pagination['last_page']], $strings['page_of']) }}</span>
        @if($pagination['current_page'] < $pagination['last_page'])
            <x-forms::link-button :url="request()->fullUrlWithQuery(['page' => $pagination['current_page'] + 1])" color="outline-secondary" size="sm">{{ $strings['next_page'] }}</x-forms::link-button>
        @else
            <span></span>
        @endif
    </nav>
@endif
