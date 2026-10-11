@props([
    'type' => 'button',
    'class' => null,
    'href' => null,
    'target' => '_self',
    'color' => 'primary',
    'variant' => 'default',
    'size' => 'default',
    'icon' => null,
    'label' => null,
    'disabled' => false,
    'buttonAttributes' => [],
])

@php
    $forwardedAttributes = $buttonAttributes instanceof \Illuminate\View\ComponentAttributeBag
        ? $buttonAttributes : new \Illuminate\View\ComponentAttributeBag($buttonAttributes);
    $class = twMerge($forwardedAttributes->get('class'), $class ?? '');
    $attributes = $attributes->merge($forwardedAttributes->except(['class', 'type'])->getAttributes());
    $type = $forwardedAttributes->get('type', $type);
    $disabled = filter_var($disabled, FILTER_VALIDATE_BOOLEAN)
        || ($attributes->has('disabled') && $attributes->get('disabled') !== false && $attributes->get('disabled') !== 'false');
    $attributes = $attributes->except(['class', 'disabled']);

    // Keep old callers working while size and colour become independent choices.
    if (in_array($color, ['primary-sm', 'primary-outline-sm'], true)) {
        $color = substr($color, 0, -3);
        if ($size === 'default') $size = 'small';
    }
    if ($size === 'compact') $size = 'small';

    $colors = [
        'mast' => 'hover:bg-sky-50 focus-visible:outline-white border border-white/70 bg-white text-primary-color-dark',
        'outline' => 'hover:bg-gray-500 text-gray-800 border border-gray-400 bg-white hover:text-white',
        'primary' => 'hover:bg-primary-color-dark bg-primary-color text-white',
        'accent' => 'hover:bg-orange-600 bg-orange-500 text-white',
        'primary-outline' => 'hover:bg-primary-color-dark text-primary-color border border-primary-color bg-white hover:text-white',
        'secondary' => 'hover:bg-gray-100 border border-gray-300 bg-white text-gray-800',
        'purple' => 'hover:bg-violet-700 bg-violet-600 text-white',
        'purple-outline' => 'hover:bg-violet-700 text-violet-700 border border-violet-300 bg-white hover:text-white',
        'danger' => 'hover:bg-danger-color-dark bg-danger-color text-white',
        'danger-outline' => 'hover:bg-danger-color-dark text-danger-color border border-danger-color bg-white hover:text-white',
        'success' => 'hover:bg-success-color-dark bg-success-color text-white',
        'dark' => 'hover:bg-gray-900 bg-gray-800 text-white',
        'orange' => 'hover:bg-orange-800 bg-orange-600 text-white',
        'yellow' => 'hover:bg-yellow-800 bg-yellow-600 text-white',
        'yellow-outline' => 'hover:bg-yellow-800 text-yellow-700 border border-yellow-300 bg-white hover:text-white',
        'rose' => 'hover:bg-rose-800 bg-rose-600 text-white',
    ];
    $softColors = [
        'primary' => 'bg-sky-50 text-primary-color hover:bg-sky-100',
        'secondary' => 'bg-slate-100 text-slate-600 hover:bg-slate-200',
        'danger' => 'bg-rose-50 text-rose-700 hover:bg-rose-100',
        'yellow' => 'bg-amber-50 text-amber-700 hover:bg-amber-100',
        'success' => 'bg-emerald-50 text-emerald-700 hover:bg-emerald-100',
    ];
    $textColors = [
        'primary' => 'text-primary-color hover:text-primary-color-dark',
        'secondary' => 'text-gray-500 hover:text-gray-700',
        'danger' => 'text-rose-700 hover:text-rose-900',
        'yellow' => 'text-amber-700 hover:text-amber-900',
        'success' => 'text-emerald-700 hover:text-emerald-900',
    ];
    $sizes = [
        'default' => 'px-8 py-1 text-sm leading-6 shadow-sm',
        'small' => 'px-3 py-1.5 text-xs leading-4 shadow-none',
        'large' => 'px-6 py-2.5 text-sm leading-6 shadow-sm',
        'icon' => 'inline-flex size-11 shrink-0 items-center justify-center p-0 text-sm leading-none shadow-none',
        'icon-small' => 'inline-flex size-8 shrink-0 items-center justify-center p-0 text-sm leading-none shadow-none',
    ];
    $iconOnly = in_array($size, ['icon', 'icon-small'], true);
    $plain = in_array($variant, ['plain', 'text'], true) || $type === 'link';
    $baseClasses = 'cursor-pointer focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary-color transition';
    $baseClasses .= $plain ? '' : ' flex items-center justify-center whitespace-nowrap rounded-md text-center font-semibold';
    $colorClasses = match (true) {
        $variant === 'plain' => '',
        $variant === 'text' => $textColors[$color] ?? $textColors['secondary'],
        $type === 'link' => 'text-sky-700 hover:text-sky-900',
        $variant === 'soft' => $softColors[$color] ?? $softColors['secondary'],
        default => $colors[$color] ?? $colors['primary'],
    };
    $sizeClasses = $plain && $size === 'default' ? '' : ($sizes[$size] ?? $sizes['default']);
    $classes = twMerge($baseClasses, $colorClasses, $sizeClasses, $class ?? '');

    $label = trim((string) ($label ?? ''));
    $icon = trim((string) ($icon ?? ''));
    if ($icon !== '' && !str_contains($icon, 'fa-')) $icon = 'fa-'.$icon;
    if ($icon !== '' && preg_match('/\bfa-(?:solid|regular|brands|light|thin|duotone)\b/', $icon) !== 1) $icon = 'fa-solid '.$icon;
    if ($iconOnly) {
        $accessibleLabel = $label ?: (string) $attributes->get('aria-label', $attributes->get('title', ''));
        if ($accessibleLabel !== '') {
            $attributes = $attributes->merge(['aria-label' => $accessibleLabel, 'title' => $accessibleLabel]);
        }
    }

    $boundHref = $attributes->get('x-bind:href', $attributes->get(':href'));
    $isLink = $type === 'link' || ($href !== null && $href !== '') || $boundHref !== null;
    $hrefValue = \App\Support\UiLink::href($href ?? ($type === 'link' ? '#' : null));
    $disabled = $disabled || ($isLink && $boundHref === null && $hrefValue === null);
    $boundDisabled = $attributes->get('x-bind:disabled', $attributes->get(':disabled'));
    $rel = \App\Support\UiLink::rel($attributes->get('rel'));
    if ($isLink) {
        $attributes = $attributes->except(['rel', 'x-bind:disabled', ':disabled']);
        if ($boundHref !== null) {
            $attributes = $attributes->except(['x-bind:href', ':href'])->merge(['x-bind:href' => 'SM.safeHref('.$boundHref.')']);
        }
        if ($disabled) {
            $attributes = $attributes->except(['x-bind:href', ':href'])->merge(['aria-disabled' => 'true', 'tabindex' => '-1']);
        } elseif ($boundDisabled !== null) {
            $attributes = $attributes->merge([
                'x-bind:aria-disabled' => '('.$boundDisabled.") ? 'true' : 'false'",
                'x-bind:tabindex' => '('.$boundDisabled.') ? -1 : null',
            ]);
        }
    }
@endphp

@if($isLink)
    <a @if(!$disabled && $hrefValue !== null) href="{{ $hrefValue }}" @endif target="{{ $target }}" rel="{{ $rel }}" class="{{ $classes }}" data-ui-button {{ $attributes }}>
        @if($icon !== '')<i class="{{ twMerge($icon, !$iconOnly && ($slot->isNotEmpty() || $label !== '') ? 'mr-2' : '') }}" aria-hidden="true"></i>@endif
        @if($slot->isNotEmpty()){{ $slot }}@elseif(!$iconOnly && $label !== ''){{ $label }}@endif
    </a>
@else
    <button type="{{ in_array($type, ['button', 'submit', 'reset'], true) ? $type : 'button' }}" class="{{ $classes }}" data-ui-button @disabled($disabled) {{ $attributes }}>
        @if($icon !== '')<i class="{{ twMerge($icon, !$iconOnly && ($slot->isNotEmpty() || $label !== '') ? 'mr-2' : '') }}" aria-hidden="true"></i>@endif
        @if($slot->isNotEmpty()){{ $slot }}@elseif(!$iconOnly && $label !== ''){{ $label }}@endif
    </button>
@endif
