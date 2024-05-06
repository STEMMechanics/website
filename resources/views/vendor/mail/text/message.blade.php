<x-mail::layout>
{{-- Body --}}
{{ $slot }}

@isset($subcopy)
<x-slot:subcopy>
{{ $subcopy }}
</x-slot:subcopy>
@endisset


{{-- Footer --}}
<x-slot:footer>
<x-mail::footer>

This email was sent to {{ $email }}

STEMMechanics | 1/4 Jordan Street | Edmonton, QLD 4869 Australia
© {{ date('Y') }} {{ config('app.name') }}. {{ __('All rights reserved.') }}

@isset($unsubscribe) Unsubscribe: {{ $unsubscribe }}@endisset
</x-mail::footer>
</x-slot:footer>
</x-mail::layout>
