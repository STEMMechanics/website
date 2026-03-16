@component('mail::message')
# Low Stock Warning

The following store products are at or below their low-stock alert threshold.

@foreach($products as $product)
### {{ $product['title'] }}

- **Type:** {{ $product['product_type_label'] }}
- **Available now:** {{ $product['available'] !== null ? $product['available'] : 'Not tracked' }}
- **Alert threshold:** {{ $product['low_stock_threshold'] }}
- **Awaiting fulfilment:** {{ $product['awaiting'] }}
- **Reserved now:** {{ $product['reserved'] }}
- **Backordered:** {{ $product['backorder'] }}
- **Preordered:** {{ $product['preorder'] }}
@if($product['notes_excerpt'])
- **Private notes:** {{ $product['notes_excerpt'] }}
@endif

Admin: {{ $product['edit_url'] }}

@endforeach

Thanks,<br>
{{ config('app.name') }}
@endcomponent
