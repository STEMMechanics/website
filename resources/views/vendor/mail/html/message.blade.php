<x-mail::layout>
{{-- Header --}}
<x-slot:header>
<x-mail::header :url="config('app.url')"/>
</x-slot:header>

{{-- Body --}}
{{ Illuminate\Mail\Markdown::parse($slot) }}

{{-- Subcopy --}}
@isset($subcopy)
<x-slot:subcopy>
{{ Illuminate\Mail\Markdown::parse($subcopy) }}
</x-slot:subcopy>
@endisset

{{-- Footer --}}
<x-slot:footer>
<x-mail::footer>
@php
    $footerEmail = trim((string) ($email ?? ($message?->to[0]['address'] ?? '')));
@endphp
<p>
@if($footerEmail !== '')
This email was sent to <a href="mailto:{{ $footerEmail }}">{{ $footerEmail }}</a><br />
@endif
<a href="{{ route('index') }}">{{ config('app.name') }}</a> | 63 Dalton Street | Westcourt, QLD 4870 Australia<br />
© {{ date('Y') }} {{ config('app.name') }}. {{ __('All rights reserved.') }}<br />
<a href="{{ route('privacy') }}">Privacy Policy</a> | <a href="{{ route('terms-conditions') }}">Terms & Conditions</a> @isset($unsubscribe) | <a href="{{ $unsubscribe }}">Unsubscribe</a>@endisset
</p>
</x-mail::footer>
</x-slot:footer>
</x-mail::layout>
