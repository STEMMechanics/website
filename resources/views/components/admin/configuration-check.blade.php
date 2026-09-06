@props(['check'])
@php
    $status = $check['status'];
    $statusLabel = ['pass' => 'Correct', 'fail' => 'Needs attention', 'review' => 'Review needed'][$status];
    $color = ['pass' => 'success', 'fail' => 'danger', 'review' => 'warning'][$status];
@endphp
<article class="min-w-0 rounded-lg border border-gray-200 bg-white p-4">
    <div class="flex flex-wrap items-start justify-between gap-2">
        <h4 class="min-w-0 font-semibold text-gray-900">{{ $check['label'] }}</h4>
        <x-ui.badge :color="$color" class="shrink-0">{{ $statusLabel }}</x-ui.badge>
    </div>
    <p class="mt-2 wrap-break-word text-xs text-gray-500"><code>{{ $check['setting'] }}</code></p>
    <p class="mt-2 text-sm text-gray-700">{{ $check['instruction'] }}</p>
</article>
