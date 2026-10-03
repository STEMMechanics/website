@props([
    'icon' => 'fa-solid fa-handshake',
    'title',
    'description',
    'actionUrl' => null,
    'actionLabel' => null,
])

<div class="flex flex-wrap items-start justify-between gap-4">
    <div class="flex min-w-0 items-start gap-4">
        <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl bg-sky-50 text-xl text-primary-color">
            <i class="{{ $icon }}" aria-hidden="true"></i>
        </div>
        <div class="min-w-0">
            <h2 class="text-2xl font-semibold text-gray-900">{{ $title }}</h2>
            <p class="mt-2 max-w-2xl text-sm leading-6 text-gray-600">{{ $description }}</p>
        </div>
    </div>
    @if($actionUrl && $actionLabel)
        <a href="{{ $actionUrl }}" class="shrink-0 text-sm font-medium text-primary-color hover:underline">{{ $actionLabel }}</a>
    @endif
</div>
