@props([
    'title' => '',
    'subtitle' => '',
    'open' => false,
])

@php
    $title = trim((string) $title);
    $subtitle = trim((string) $subtitle);
@endphp

<details
    {{ $attributes->class(['ui-collapsible-section']) }}
    @if($open) open @endif
>
    <summary class="ui-collapsible-section__summary">
        <div class="ui-collapsible-section__summary-title">
            @if($title !== '')
                <span class="ui-collapsible-section__summary-text ui-collapsible-section__summary-text--title">{{ $title }}</span>
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

        <div class="ui-collapsible-section__summary-actions">
            <i class="fa-solid fa-chevron-down ui-collapsible-section__chevron"></i>
        </div>
    </summary>

    <div class="ui-collapsible-section__content">
        {{ $slot }}
    </div>
</details>
