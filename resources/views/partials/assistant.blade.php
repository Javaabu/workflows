{{-- The AI assistant: a description, sent to the assistant route --}}
<x-forms::card :title="$title" data-workflows-assistant>
    <x-forms::form :action="$url" method="POST">
        <div class="flex flex-col gap-3">
            <p class="text-sm text-muted-foreground">{{ $help }}</p>
            <x-forms::textarea name="description" :label="$title" rows="4" required dir="auto" :placeholder="$strings['assistant_placeholder']" />
            <div>
                <x-forms::submit>{{ $strings['assistant_submit'] }}</x-forms::submit>
            </div>
        </div>
    </x-forms::form>
</x-forms::card>
