@props([
    'label' => '',
    'iconClass' => 'fa-solid fa-tag',
    'href' => null,
])

<x-ui.badge color="gray" variant="outline" size="xs" :icon="$iconClass" :href="$href" {{ $attributes }}>
    {{ $label }}
</x-ui.badge>
