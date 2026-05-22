@php
    $notice = trim((string) ($notice ?? ''));
@endphp

@if($notice !== '')
    <div class="flex justify-center px-4 pt-4">
        <div class="flex w-full max-w-7xl items-start gap-2 rounded border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-950 justify-center shadow">
            <i class="fa-solid fa-circle-info mt-1 text-amber-600" aria-hidden="true"></i>
            <div>{{ $notice }}</div>
        </div>
    </div>
@endif
