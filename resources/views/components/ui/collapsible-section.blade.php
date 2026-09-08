@props([
    'title' => '',
    'titleExpression' => null,
    'subtitle' => '',
    'open' => false,
    'variant' => 'default',
])

@php
    $title = trim((string) $title);
    $subtitle = trim((string) $subtitle);
@endphp

<details
    {{ $attributes->class(['ui-collapsible-section', 'ui-collapsible-section--panel' => $variant === 'panel', 'ui-collapsible-section--product' => $variant === 'product']) }}
    @if($open) open @endif
>
    <summary class="ui-collapsible-section__summary">
        @if($variant === 'product')
            <i class="fa-solid fa-chevron-right ui-collapsible-section__chevron" aria-hidden="true"></i>
        @endif
        <div class="ui-collapsible-section__summary-title">
            @if($title !== '')
                <span class="ui-collapsible-section__summary-text ui-collapsible-section__summary-text--title" @if($titleExpression) x-text="{{ $titleExpression }}" @endif>{{ $title }}</span>
            @endif

            @if(isset($summary))
                <span class="ui-collapsible-section__summary-text ui-collapsible-section__summary-text--subtitle">
                    {{ $summary }}
                </span>
            @elseif($subtitle !== '')
                <span class="ui-collapsible-section__summary-text ui-collapsible-section__summary-text--subtitle">
                    {{ $subtitle }}
                </span>
            @endif
        </div>

        @if($variant !== 'product')
            <div class="ui-collapsible-section__summary-actions">
                <i class="fa-solid fa-chevron-down ui-collapsible-section__chevron" aria-hidden="true"></i>
            </div>
        @endif
    </summary>

    <div class="ui-collapsible-section__content">
        {{ $slot }}
    </div>
</details>
