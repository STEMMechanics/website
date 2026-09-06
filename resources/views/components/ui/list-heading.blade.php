@props(['label', 'scope' => null, 'field' => null, 'suffix' => null])
@php
    $centerColumn = preg_match('/^(id$|visibility|storage|status|type|amount|total|price|size|date|time|created|updated|sent|failed|received|paid|issued|due|starts|scheduled|purchased|registered|placed|allocated|unallocated|qty|quantity|used|count|order$|contacts$|workshops$|products$|actions$|confirmed$)/i', $label) === 1;
    $controls = $scope ? new \App\Services\SiteListControls($scope) : app(\App\Services\SiteListControls::class);
    $fields = $controls->fields();
    $sortName = $controls->parameter('sort');
    $directionName = $controls->parameter('direction');
    $pageReset = $controls->paginationReset();
    $aliases = ['name' => ['name', 'firstname', 'title'], 'date' => ['starts_at', 'created_at', 'received_on', 'quote_date', 'paid_on'], 'total' => ['total_amount'], 'amount' => ['total_amount', 'bas_total_amount'], 'title' => ['title', 'name'], 'type' => ['type', 'product_type', 'discount_type', 'mime_type']];
    $explicitField = $field;
    $field = isset($fields[$explicitField ?? '']) ? $explicitField : collect($fields)->search(fn ($definition) => strcasecmp($definition['label'], $label) === 0);
    if ($field === false) $field = collect($aliases[strtolower($label)] ?? [])->first(fn ($candidate) => isset($fields[$candidate]));
    $active = $field && request($sortName) === $field;
    $direction = $active && request($directionName, 'asc') === 'asc' ? 'desc' : 'asc';
@endphp
@if($field)
<th @if($centerColumn) data-column-align="center" @endif {{ $attributes }} @if($field) aria-sort="{{ $active ? (request($directionName, 'asc') === 'asc' ? 'ascending' : 'descending') : 'none' }}" @endif>
    @if($field)
        <a data-dynamic-link class="inline-flex min-h-11 items-center gap-2 whitespace-nowrap" href="{{ request()->fullUrlWithQuery([$sortName => $field, $directionName => $direction, ...$pageReset]) }}" aria-label="Sort by {{ $label }}, {{ $direction === 'asc' ? 'ascending' : 'descending' }}">{{ $label }}@if($suffix)<span class="font-normal text-xs">{{ $suffix }}</span>@endif<i class="fa-solid {{ $active ? ($direction === 'desc' ? 'fa-arrow-up' : 'fa-arrow-down') : 'fa-sort' }} text-xs {{ $active ? 'text-primary-color' : 'text-slate-400' }}" aria-hidden="true"></i></a>
    @endif
</th>

@else
<th @if($centerColumn) data-column-align="center" @endif {{ $attributes }}>{{ $label }}@if($suffix) <span class="font-normal text-xs">{{ $suffix }}</span>@endif</th>
@endif
