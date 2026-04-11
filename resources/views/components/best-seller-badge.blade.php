@props([
    'label' => 'Best seller',
])

<x-ui.badge color="warning" variant="solid" size="xxs" uppercase="true" icon="fa-solid fa-trophy" {{ $attributes }}>
    {{ $label }}
</x-ui.badge>
