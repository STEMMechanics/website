@props(['kind'])
@php
    $counts = app(\App\Services\Finance\FinanceAttention::class)->counts();
    $total = $counts[$kind];
@endphp
@if($total > 0)
    <aside class="my-4 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900" aria-label="Finance items needing attention">
        <p class="font-semibold">{{ $total }} {{ \Illuminate\Support\Str::plural($kind === 'invoices' ? 'invoice' : 'expense', $total) }} {{ $total === 1 ? 'needs' : 'need' }} attention</p>
        <div class="mt-2 flex flex-wrap gap-x-5 gap-y-2">
            @if($kind === 'invoices')
                @if($counts['overdue'])<a data-dynamic-link class="underline underline-offset-2" href="{{ route('admin.invoice.index', ['status' => ['overdue']]) }}">{{ $counts['overdue'] }} overdue</a>@endif
                @if($counts['unallocated_invoices'])<a data-dynamic-link class="underline underline-offset-2" href="{{ route('admin.invoice.index', ['allocation_state' => 'not_allocated', 'status' => ['issued', 'sent', 'paid', 'overdue', 'written_off'], 'list_total_amount_min' => '0.01']) }}">{{ $counts['unallocated_invoices'] }} missing cost centre allocation</a>@endif
            @else
                <a data-dynamic-link class="underline underline-offset-2" href="{{ route('admin.expense.index', ['allocation_state' => 'not_allocated']) }}">{{ $counts['expenses'] }} missing cost centre allocation</a>
            @endif
        </div>
    </aside>
@endif
