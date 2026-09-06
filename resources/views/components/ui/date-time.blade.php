@props(['value' => null])
@php
    $display = trim((string) ($value ?? html_entity_decode((string) $slot, ENT_QUOTES, 'UTF-8')));
    $parts = [];
    $hasTime = preg_match('/^(.+?)\s+(\d{1,2}:\d{2}(?::\d{2})?(?:\s*[ap]m)?)$/i', $display, $parts) === 1;
@endphp
<span {{ $attributes->class(['sm-date-time']) }}><span class="sm-no-break">{{ $hasTime ? $parts[1] : $display }}</span>@if($hasTime) <wbr><span class="sm-no-break">{{ $parts[2] }}</span>@endif</span>
