<x-layout>
    <x-mast>Workshops</x-mast>

    <x-container>
        <div class="flex my-4 items-center">
            <div class="flex-1">
                <x-ui.button type="link" href="{{ route('admin.workshop.create') }}">Create</x-ui.button>
            </div>
            <div class="flex-1">
                <x-ui.search name="search" label="Search" />
            </div>
        </div>

        @if($workshops->isEmpty())
            <x-none-found item="workshops" search="{{ request()->get('search') }}" />
        @else
            <x-ui.table>
                <x-slot:header>
                    <th>Title</th>
                    <th>Status</th>
                    <th>Location</th>
                    <th>Starts</th>
                    <th>Action</th>
                </x-slot:header>
                <x-slot:body>
                    @foreach ($workshops as $workshop)
                        <tr>
                            <td>{{ $workshop->title }}</td>
                            <td>{{ ucwords($workshop->status) }}</td>
                            <td>{{ $workshop->location->name }}</td>
                            <td>{{ \Carbon\Carbon::parse($workshop->starts_at)->format('M j Y, g:i a') }}</td>
                            <td>
                                <div class="flex justify-center gap-3">
                                <a href="{{ route('admin.workshop.edit', $workshop) }}" class="hover:text-primary-color"><i class="fa-solid fa-pen-to-square"></i></a>
                                <a href="#" class="hover:text-red-600" x-data x-on:click.prevent="SM.confirmDelete('{{ csrf_token() }}', 'Delete workshop?', 'Are you sure you want to delete this workshop? This action cannot be undone', '{{ route('admin.workshop.destroy', $workshop) }}')"><i class="fa-solid fa-trash"></i></a>
                                </div>
                            </td>
                        </tr>
                  @endforeach
                </x-slot:body>
            </x-ui.table>

            {{ $workshops->links() }}
        @endif

    </x-container>
</x-layout>
