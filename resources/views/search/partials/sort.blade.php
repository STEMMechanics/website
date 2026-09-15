@php
    $controls = new \App\Services\SiteListControls($scope);
    $sortName = $controls->parameter('sort');
    $directionName = $controls->parameter('direction');
@endphp
<fieldset x-show="{{ $selection }}" x-bind:disabled="!{{ $selection }}" @disabled(!$enabled)>
    <legend class="mb-3 font-semibold text-gray-900">{{ $title }}</legend>
    <div class="grid grid-cols-2 gap-4">
        <div>
            <x-ui.select :name="$sortName" label="Sort by" aria-label="{{ $title }} sort by" class="mb-0">
                <option value="">Default order</option>
                @foreach($controls->fields() as $key => $field)
                    <option value="{{ $key }}" @selected(request($sortName) === $key)>{{ $field['label'] }}</option>
                @endforeach
            </x-ui.select>
        </div>
        <div>
            <x-ui.select :name="$directionName" label="Order" aria-label="{{ $title }} sort order" class="mb-0">
                <option value="asc" @selected(request($directionName, 'asc') === 'asc')>Ascending</option>
                <option value="desc" @selected(request($directionName) === 'desc')>Descending</option>
            </x-ui.select>
        </div>
    </div>
</fieldset>
