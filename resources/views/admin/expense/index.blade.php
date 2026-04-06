<x-layout>
    <x-mast>Expenses</x-mast>

    <x-container>
        <x-ui.toolbar>
            <x-slot:left>
                <x-ui.button href="{{ route('admin.expense.create') }}">Record</x-ui.button>
            </x-slot:left>
            <x-slot:right>
                <x-ui.search name="search" label="Search" />
            </x-slot:right>
        </x-ui.toolbar>

        @if($expenses->isEmpty())
            <x-none-found item="expenses" search="{{ request()->get('search') }}" />
        @else
            <x-ui.table>
                <x-slot:header>
                    <th>Expense</th>
                    <th class="hidden md:table-cell">Supplier</th>
                    <th class="hidden md:table-cell">Invoice ID</th>
                    <th class="hidden lg:table-cell">Description</th>
                    <th>Amount <span class="font-normal text-xs">(incl GST)</span></th>
                    <th>Actions</th>
                </x-slot:header>
                <x-slot:body>
                    @foreach ($expenses as $expense)
                        <tr>
                            <td>
                                <a href="{{ route('admin.expense.edit', $expense) }}" class="font-semibold text-gray-900 hover:text-primary-color">{{ $expense->paid_on?->format('M j, Y') ?? '-' }}</a>
                                <div class="md:hidden text-xs text-gray-600 mt-1">{{ $expense->supplier ?: '-' }}</div>
                                <div class="md:hidden text-xs text-gray-600">{{ $expense->invoice_id ?: 'No invoice ID' }}</div>
                                <div class="lg:hidden text-xs text-gray-600">{{ $expense->description ?: '-' }}</div>
                                @if(! $expense->receipt_document_path)
                                    <div class="md:hidden mt-0.5 text-xs text-red-600">(No attached invoice)</div>
                                @endif
                            </td>
                            <td class="hidden md:table-cell">{{ $expense->supplier ?: '-' }}</td>
                            <td class="hidden md:table-cell">
                                <div>{{ $expense->invoice_id ?: '-' }}</div>
                                @if(! $expense->receipt_document_path)
                                    <div class="mt-0.5 text-xs text-red-600">(No attached invoice)</div>
                                @endif
                            </td>
                            <td class="hidden lg:table-cell">{{ $expense->description ?: '-' }}</td>
                            <td>
                                <div>${{ number_format((float) $expense->total_amount, 2) }}</div>
                                <div class="text-xs text-gray-600">GST: ${{ number_format((float) $expense->gst_amount, 2) }}</div>
                            </td>

                            <td>
                                <div class="flex justify-center gap-3 whitespace-nowrap">
                                    @if($expense->receipt_document_path)
                                        <a href="{{ route('admin.expense.document.view', $expense) }}" class="hover:text-primary-color" target="_blank" title="View Attachment"><i class="fa-solid fa-paperclip"></i></a>
                                    @else
                                        <span class="text-gray-300" title="No Attachment"><i class="fa-solid fa-paperclip"></i></span>
                                    @endif
                                    <a href="{{ route('admin.expense.edit', $expense) }}" class="hover:text-primary-color"><i class="fa-solid fa-pen-to-square"></i></a>
                                    <form method="POST" action="{{ route('admin.expense.destroy', $expense) }}" x-data x-on:submit.prevent="SM.confirmDelete('{{ csrf_token() }}', 'Delete expense?', 'Are you sure you want to delete this expense?', $el)">
                                        @method('DELETE')
                                        @csrf
                                        <button type="submit" class="hover:text-red-600"><i class="fa-solid fa-trash"></i></button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </x-slot:body>
            </x-ui.table>

            {{ $expenses->appends(request()->query())->links() }}
        @endif
    </x-container>
</x-layout>
