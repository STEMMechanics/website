@php
    $tabs = [
        ['title' => 'History', 'route' => route('admin.workshop.history', request()->query())],
        ['title' => 'Matrix', 'route' => route('admin.workshop.coverage', request()->query())],
    ];
@endphp

<x-layout>
    <x-mast
        title="Workshop Reports"
        backRoute="admin.workshop.index"
        backTitle="Workshops"
        :tabs="$tabs"
    />

    <x-container>
        @include('admin.workshop.partials.matrix-filters')

        @if($columns->isEmpty())
            <div class="rounded-xl border border-dashed border-gray-300 bg-gray-50 px-6 py-12 text-center">
                <i class="fa-solid fa-table-columns mb-3 text-3xl text-gray-300"></i>
                <h3 class="font-semibold text-gray-900">Select organisations to build the matrix</h3>
                <p class="mt-1 text-sm text-gray-500">The selected organisations will appear across the top, with workshops and delivery dates below.</p>
            </div>
        @elseif($rows->isEmpty())
            <x-none-found item="workshops" search="{{ request('search') }}" />
        @else
            <div class="flex items-center justify-between gap-4 mb-4 mt-8">
                <div class="text-lg font-bold">Results</div>
                <div class="flex gap-4">
                    <x-ui.button color="outline" href="{{ route('admin.workshop.coverage.csv', request()->query()) }}"><i class="fa-solid fa-file-csv mr-2"></i>CSV</x-ui.button>
                    <x-ui.button color="outline" href="{{ route('admin.workshop.coverage.pdf', request()->query()) }}" target="_blank"><i class="fa-regular fa-file-pdf mr-2"></i>PDF</x-ui.button>
                </div>
            </div>
            <div class="overflow-x-auto rounded-xl border border-gray-200 bg-white shadow-sm">
                <table class="min-w-full table-fixed border-collapse text-sm">
                    <thead>
                        <tr class="bg-gray-50">
                            <th class="sticky left-0 z-20 w-72 border-b border-r border-gray-200 bg-gray-50 px-3 py-3 text-left align-middle">Workshop</th>
                            @foreach($columnGroups as $group)
                                <th colspan="{{ $group['columns']->count() }}" class="border-b border-r border-gray-200 px-3 py-3 text-center align-middle last:border-r-0">
                                    {{ $group['name'] }}
                                </th>
                            @endforeach
                        </tr>
                        <tr class="bg-gray-50">
                            <th class="sticky left-0 z-20 border-b border-r border-gray-200 bg-gray-50 px-3 py-2 text-left text-xs text-gray-500">Location</th>
                            @foreach($columns as $column)
                                <th class="w-44 border-b border-r border-gray-200 px-3 py-2 text-center text-xs font-medium text-gray-600 last:border-r-0">
                                    {{ $column['location_name'] }}
                                </th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($rows as $row)
                            <tr class="even:bg-gray-50/50">
                                <th class="sticky left-0 z-10 border-b border-r border-gray-200 bg-white px-3 py-3 text-left font-semibold text-gray-900">{{ $row['title'] }}</th>
                                @foreach($columns as $column)
                                    @php($deliveries = $row['cells'][$column['id']] ?? [])
                                    <td class="border-b border-r border-gray-200 px-3 py-2 text-center align-top last:border-r-0">
                                        @forelse($deliveries as $delivery)
                                            <a
                                                href="{{ $delivery['edit_url'] }}"
                                                target="_blank"
                                                rel="noopener noreferrer"
                                                class="mb-1 inline-block rounded-full bg-sky-50 px-2 py-1 text-xs text-sky-800 hover:bg-sky-100 hover:underline whitespace-nowrap"
                                            >{{ $delivery['date'] }}</a>
                                        @empty
                                            <span class="text-gray-300">-</span>
                                        @endforelse
                                    </td>
                                @endforeach
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </x-container>
</x-layout>
