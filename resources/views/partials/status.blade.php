@if(! empty($status))
    <x-forms::alert type="success" role="status">{{ $status }}</x-forms::alert>
@endif
@if($errors->any() && $errors->hasAny(['definition', 'transition', 'description', 'workflow']))
    <x-forms::alert type="danger" role="alert">
        @foreach(['definition', 'transition', 'description', 'workflow'] as $key)
            @foreach($errors->get($key) as $message)
                <p>{{ $message }}</p>
            @endforeach
        @endforeach
    </x-forms::alert>
@endif
