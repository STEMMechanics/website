@props(['workshop'])
@php
    $state = \App\Support\WorkshopNavigation::state($workshop);
    $needsReview = ($state['ready'] && ! $state['current']) || $state['status'] === 'Allocation needs review';
@endphp
@if($needsReview)
    <aside class="mb-4 flex items-start gap-3 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900" aria-label="Workshop allocation review">
        <i class="fa-solid fa-circle-exclamation mt-0.5 shrink-0" aria-hidden="true"></i>
        <div class="min-w-0">
            <p class="font-semibold">{{ $state['status'] === 'Allocation needs review' ? 'Allocation needs review' : 'Allocation ready for review' }}</p>
            @unless(request()->routeIs('admin.workshop.allocation.edit'))
                <a class="mt-2 inline-block underline underline-offset-2" href="{{ route('admin.workshop.allocation.edit', $workshop) }}">Review allocation</a>
            @endunless
        </div>
    </aside>
@endif
