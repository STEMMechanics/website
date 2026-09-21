@props(['kind', 'total' => null])
@php
    $counts = $total === null ? app(\App\Services\Finance\FinanceAttention::class)->counts() : [];
    $total ??= $counts[$kind];
    $itemLabel = $kind === 'product-stock' ? 'products' : $kind;
@endphp
@if($total > 0)
    <aside class="mb-4 flex items-start gap-3 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900" aria-label="{{ $kind === 'product-stock' ? 'Product stock needing attention' : ($kind === 'orders' ? 'Store orders needing attention' : 'Finance items needing attention') }}">
        <i class="fa-solid fa-circle-exclamation mt-0.5 shrink-0" aria-hidden="true"></i>
        <div class="min-w-0">
            <p class="font-semibold">{{ $total }} {{ \Illuminate\Support\Str::plural(\Illuminate\Support\Str::singular($itemLabel), $total) }} {{ $total === 1 ? 'needs' : 'need' }} attention</p>
            <div class="mt-2 flex flex-wrap gap-x-5 gap-y-2">
                @if($kind === 'invoices')
                    @if($counts['overdue'])<a data-dynamic-link class="underline underline-offset-2" href="{{ route('admin.invoice.index', ['status' => ['overdue']]) }}">{{ $counts['overdue'] }} overdue</a>@endif
                    @if($counts['unallocated_invoices'])<a data-dynamic-link class="underline underline-offset-2" href="{{ route('admin.invoice.index', ['allocation_state' => 'not_allocated', 'status' => ['issued', 'sent', 'paid', 'overdue', 'written_off'], 'list_total_amount_min' => '0.01']) }}">{{ $counts['unallocated_invoices'] }} missing cost centre allocation</a>@endif
                @elseif($kind === 'product-stock')
                    <a data-dynamic-link class="underline underline-offset-2" href="{{ route('admin.shop.product.index', ['status_scope' => 'current', 'inventory' => 'actionable']) }}">{{ $total }} with low stock</a>
                @elseif($kind === 'orders')
                    <p>Review orders awaiting preparation, shipping, collection or a quote.</p>
                @elseif($kind === 'products')
                    <a data-dynamic-link class="underline underline-offset-2" href="{{ route('admin.shop.product.index', ['status_scope' => 'current', 'allocation_state' => 'needs_review']) }}">{{ $total }} missing or incomplete cost centre {{ \Illuminate\Support\Str::plural('allocation', $total) }}</a>
                @elseif($kind === 'workshops')
                    <a class="underline underline-offset-2" href="{{ route('admin.workshop.index', ['view' => 'list', 'allocation_state' => 'needs_review']) }}">{{ $total }} {{ \Illuminate\Support\Str::plural('workshop allocation', $total) }} ready for review</a>
                @else
                    <a data-dynamic-link class="underline underline-offset-2" href="{{ route('admin.expense.index', ['allocation_state' => 'not_allocated']) }}">{{ $counts['expenses'] }} missing cost centre allocation</a>
                @endif
            </div>
        </div>
    </aside>
@endif
