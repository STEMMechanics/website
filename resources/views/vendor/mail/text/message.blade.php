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
@php
    $footerEmail = trim((string) ($email ?? ($message?->to[0]['address'] ?? '')));
@endphp

@if($footerEmail !== '')
This email was sent to {{ $footerEmail }}
@endif

STEMMechanics | 63 Dalton Street | Westcourt, QLD 4870 Australia
© {{ date('Y') }} {{ config('app.name') }}. {{ __('All rights reserved.') }}

@isset($unsubscribe) Unsubscribe: {{ $unsubscribe }}@endisset
</x-mail::footer>
</x-slot:footer>
</x-mail::layout>
