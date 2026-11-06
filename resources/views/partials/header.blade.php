{{-- A page's heading: an optional back link, the title, a description and actions. Every page has one, so it loads the package styles --}}
@manifestRequire('workflows::styles')
<div class="flex flex-wrap items-start justify-between gap-4">
    <div class="flex min-w-0 flex-col gap-1">
        @isset($back_url)
            <a href="{{ $back_url }}" class="text-sm text-muted-foreground hover:underline">{{ $back_label ?? __('Back') }}</a>
        @endisset
        <h1 class="text-2xl font-semibold" dir="auto">{{ $title }}</h1>
        @if(! empty($description))
            <p class="text-muted-foreground" dir="auto">{{ $description }}</p>
        @endif
    </div>
    @isset($actions)
        <div class="flex flex-wrap items-center gap-2">{{ $actions }}</div>
    @endisset
</div>
