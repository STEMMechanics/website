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
        @include('admin.workshop.partials.report-filters', ['reportType' => 'history'])

        @if($workshops->isEmpty())
            <x-none-found item="workshops" search="{{ request('search') }}" />
        @else
            <div class="flex items-center justify-between gap-4 mb-4 mt-8">
                <div class="text-lg font-bold">Results</div>
                <div class="flex gap-4">
                    <x-ui.button color="outline" href="{{ route('admin.workshop.history.csv', request()->query()) }}"><i class="fa-solid fa-file-csv mr-2"></i>CSV</x-ui.button>
                    <x-ui.button color="outline" href="{{ route('admin.workshop.history.pdf', request()->query()) }}" target="_blank"><i class="fa-regular fa-file-pdf mr-2"></i>PDF</x-ui.button>
                </div>
            </div>
            <x-ui.table>
                <x-slot:header>
                    <th>Date</th>
                    <th>Workshop</th>
                    <th class="hidden md:table-cell">Hosted for</th>
                    <th class="hidden lg:table-cell">Requested by</th>
                    <th>Location</th>
                    <th>Status</th>
                </x-slot:header>
                <x-slot:body>
                    @foreach($workshops as $workshop)
                        <tr>
                            <td class="whitespace-nowrap">{{ $workshop->starts_at?->format('d M Y') ?? '-' }}</td>
                            <td><a class="text-primary-color hover:underline" href="{{ route('admin.workshop.edit', $workshop) }}">{{ $workshop->title }}</a></td>
                            <td class="hidden md:table-cell">{{ $workshop->hostedFor?->name ?? '-' }}</td>
                            <td class="hidden lg:table-cell">{{ $workshop->requestedBy?->getName() ?? '-' }}</td>
                            <td>{{ $workshop->getLocationName() }}</td>
                            <td>{{ $workshop->adminStatusLabel() }}</td>
                        </tr>
                    @endforeach
                </x-slot:body>
            </x-ui.table>
            {{ $workshops->links() }}
        @endif
    </x-container>
</x-layout>
