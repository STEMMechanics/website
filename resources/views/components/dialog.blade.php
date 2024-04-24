<div class="flex items-center justify-center flex-grow py-24">
    <div class="w-full mx-2 max-w-lg p-8 pb-6 bg-white rounded-md shadow-deep">
        @isset($title)
            <h2 class="text-2xl font-bold mb-4 text-center">{{ $title }}</h2>
        @endisset
        @isset($header)
            <div class="flex items-center gap-4 mb-4">
                {{ $header }}
            </div>
        @endisset

        @isset($formaction)
            @isset($id)
                <form method="POST" action="{{ $formaction }}" id="{{ $id }}">
            @else
                <form method="POST" action="{{ $formaction }}">
            @endisset
                @csrf
                {{ $slot }}

                @isset($footer)
                    <div class="flex flex-col sm:flex-row items-center justify-between gap-4">
                        {{ $footer }}
                    </div>
                @endisset
            </form>
        @else
            {{ $slot }}

            @isset($footer)
                <div class="{{ twMerge(explode(' ', 'flex flex-col items-center gap-4 ' . ($footer->attributes->get('center', false) ? ' justify-center' : ' justify-between')), $footer->attributes->get('class', '')) }}">
                    {{ $footer }}
                </div>
            @endisset
        @endisset
    </div>
</div>
