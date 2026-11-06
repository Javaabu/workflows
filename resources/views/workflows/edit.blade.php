@extends($layout)

@section($layout_section)
    <div class="flex flex-col gap-6" data-workflows-settings>
        @include('workflows::partials.header', ['title' => $title, 'back_url' => $back_url, 'back_label' => $strings['back']])
        @include('workflows::partials.status')

        {{ $form }}
    </div>
@endsection
