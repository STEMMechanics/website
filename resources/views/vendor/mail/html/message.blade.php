@php
    $hideHeader = (bool) ($hideHeader ?? false);
@endphp

<x-mail::layout>
{{-- Header --}}
@unless($hideHeader)
<x-slot:header>
<x-mail::header :url="config('app.url')"/>
</x-slot:header>
@endunless

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
<p style="margin:0; text-align:center; font-size:12px; line-height:1.7; color:#6b7280;">
@if($footerEmail !== '')
This email was sent to <a href="mailto:{{ $footerEmail }}" style="color:#6b7280;">{{ $footerEmail }}</a><br />
@endif
<a href="{{ route('index') }}" style="color:#6b7280; text-decoration:none;">{{ config('app.name') }}</a> | 63 Dalton Street | Westcourt, QLD 4870 Australia<br />
© {{ date('Y') }} {{ config('app.name') }}. {{ __('All rights reserved.') }}<br />
<a href="{{ route('privacy') }}" style="color:#6b7280;">Privacy Policy</a> | <a href="{{ route('terms-conditions') }}" style="color:#6b7280;">Terms & Conditions</a> @isset($unsubscribe) | <a href="{{ $unsubscribe }}" style="color:#6b7280;">Unsubscribe</a>@endisset
</p>
</x-mail::footer>
</x-slot:footer>
</x-mail::layout>
