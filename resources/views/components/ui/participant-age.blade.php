@props(['name' => null, 'value' => null])

<div class="relative" x-data="{ ageHelpOpen: false }" x-id="['participant-age', 'participant-age-help']" x-on:keydown.escape.window="if (ageHelpOpen) { ageHelpOpen = false; $refs.ageHelpButton.focus(); }">
    <div class="mb-1 flex items-center gap-1.5">
        <label x-bind:for="$id('participant-age')" class="text-sm font-medium">Age <span class="text-xs font-normal text-gray-500">(optional)</span></label>
        <x-ui.button variant="plain" type="button" class="inline-flex h-6 w-6 items-center justify-center text-gray-500 hover:text-gray-900" x-ref="ageHelpButton" x-on:click="ageHelpOpen = !ageHelpOpen" x-bind:aria-expanded="ageHelpOpen" x-bind:aria-controls="$id('participant-age-help')" aria-label="About participant age">
            <i class="fa-solid fa-circle-info" aria-hidden="true"></i>
        </x-ui.button>
    </div>
    <div x-bind:id="$id('participant-age-help')" x-show="ageHelpOpen" x-cloak x-on:click.outside="ageHelpOpen = false" role="note" class="absolute left-0 top-8 z-20 w-64 max-w-full rounded-lg border border-gray-200 bg-white p-3 text-sm font-normal text-gray-700 shadow-lg">
        Age in years helps us tailor the workshop activities.
    </div>
    <x-ui.input-control type="number" min="0" max="120" step="1" inputmode="numeric" :name="$name" :value="$value" x-bind:id="$id('participant-age')" {{ $attributes->class(['max-w-28']) }} />
</div>
