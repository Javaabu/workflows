@extends($layout)

@section($layout_section)
    <div class="flex flex-col gap-6" data-workflows-start="{{ $workflow['key'] }}">
        @include('workflows::partials.header', ['title' => $strings['title'], 'description' => $subject['label'] ?? $workflow['description'], 'back_url' => $urls['back'], 'back_label' => $strings['back']])
        @include('workflows::partials.status')

        <form method="POST" action="{{ $urls['store'] }}" enctype="multipart/form-data" class="flex flex-col gap-4">
            @csrf
            @if($form)
                {{ $form }}
            @endif
            <x-forms::textarea name="comment" :label="$strings['comment']" rows="3" dir="auto" />
            <div>
                <x-forms::button type="submit" start-icon="play">{{ $strings['start'] }}</x-forms::button>
            </div>
        </form>
    </div>
@endsection
