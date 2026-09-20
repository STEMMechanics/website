<div class="grid grid-cols-2 gap-3 lg:grid-cols-5">
    @foreach($summary as $label => $value)
        <div class="rounded-xl bg-gray-50 p-3">
            <p class="text-xs text-gray-600">{{ $label }}</p>
            <p class="mt-1 text-xl font-semibold text-gray-900">{{ $value }}</p>
        </div>
    @endforeach
</div>
<p class="mt-3 text-xs text-gray-500">Carts started in this date range, with their latest outcome. Inactive means no activity for 24 hours. Values are item subtotals before discounts and delivery. Payment issues can overlap other counts. Tracking begins when this feature is enabled; earlier carts are not reconstructed.</p>
