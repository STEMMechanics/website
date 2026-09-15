@php
    $controls = new \App\Services\SiteListControls($scope);
@endphp
<fieldset class="min-w-0 rounded-xl border border-gray-200 bg-white p-4" x-show="{{ $selection }}" x-bind:disabled="!{{ $selection }}" @disabled(!$enabled)>
    <legend class="px-1 font-semibold text-gray-900">{{ $title }}</legend>
    <p class="mb-4 text-sm text-gray-500">These controls apply only to {{ $description }}.</p>
    <div class="grid gap-4 sm:grid-cols-2">
        @foreach(collect($controls->filterFields())->reject(fn ($field) => $field['type'] === 'text') as $key => $field)
            <div class="min-w-0">
                <label for="{{ $key }}" class="mb-1 block text-sm font-medium text-gray-700">{{ $field['label'] }}</label>
                <x-ui.input-control data-search-filter :id="$key" :name="$key" :type="in_array($field['type'], ['date', 'number']) ? $field['type'] : 'text'" :value="request($key)" :step="$field['type'] === 'number' ? 'any' : null" />
            </div>
        @endforeach

    </div>
</fieldset>
