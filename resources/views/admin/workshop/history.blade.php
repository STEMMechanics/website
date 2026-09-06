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
        <x-ui.dynamic-list name="admin-workshop-history">

        <x-ui.collection-controls class="my-4">
            <x-slot:filterForm>@include('admin.workshop.partials.report-filters', ['reportType' => 'history'])</x-slot:filterForm>
        </x-ui.collection-controls>

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
            <x-ui.table variant="listing">
                <x-slot:header>
                    <x-ui.list-heading class="text-center!" label="Date" />
                    <x-ui.list-heading label="Workshop" />
                    <x-ui.list-heading class="hidden md:table-cell" label="Hosted for" />
                    <x-ui.list-heading class="hidden lg:table-cell" label="Requested by" />
                    <x-ui.list-heading label="Location" />
                    <x-ui.list-heading class="text-center!" label="Status" />
                </x-slot:header>
                <x-slot:body>
                    @foreach($workshops as $workshop)
                        <tr>
                            <td class="whitespace-nowrap text-center!"><x-ui.date-time>{{ $workshop->starts_at?->format('d M Y') ?? '-' }}</x-ui.date-time></td>
                            <td><a class="text-primary-color hover:underline" href="{{ route('admin.workshop.edit', $workshop) }}">{{ $workshop->title }}</a></td>
                            <td class="hidden md:table-cell">{{ $workshop->hostedFor?->name ?? '-' }}</td>
                            <td class="hidden lg:table-cell">{{ $workshop->requestedBy?->getName() ?? '-' }}</td>
                            <td>{{ $workshop->getLocationName() }}</td>
                            <td class="text-center!">{{ $workshop->adminStatusLabel() }}</td>
                        </tr>
                    @endforeach
                </x-slot:body>
            </x-ui.table>
            <x-ui.list-pagination :paginator="$workshops" />
        @endif

        </x-ui.dynamic-list>
    </x-container>
</x-layout>
