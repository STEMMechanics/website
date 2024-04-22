<x-mail::layout>
{{-- Header --}}
<x-slot:header>
<x-mail::header :url="config('app.url')" username="{{ $username }}" />
</x-slot:header>

{{-- Body --}}
{{ Illuminate\Mail\Markdown::parse($slot) }}

{{-- Subcopy --}}
@isset($subcopy)
<x-slot:subcopy>
<x-mail::subcopy>
{{ $subcopy }}
</x-mail::subcopy>
</x-slot:subcopy>
@endisset

{{-- Footer --}}
<x-slot:footer>
<x-mail::footer>
<p>This email was sent to <a href="mailto:{{ $email }}">{{ $email }}</a><br />
<a href="{{ config('app.url') }}">{{ config('app.name') }}</a> | 1/4 Jordan Street | Edmonton, QLD 4869 Australia<br />
© {{ date('Y') }} {{ config('app.name') }}. {{ __('All rights reserved.') }}<br />
<a href="{{ config('app.url') }}/privacy">Privacy Policy</a>
</p>
</x-mail::footer>
</x-slot:footer>
</x-mail::layout>
