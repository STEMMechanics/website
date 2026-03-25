<x-layout>
    <x-mast>Workshops</x-mast>

    <x-container>
        <x-ui.toolbar>
            <x-slot:left>
                <x-ui.button href="{{ route('admin.workshop.create') }}">Create</x-ui.button>
            </x-slot:left>
            <x-slot:right>
                <x-ui.search name="search" label="Search" />
            </x-slot:right>
        </x-ui.toolbar>

        @if($workshops->isEmpty())
            <x-none-found item="workshops" search="{{ request()->get('search') }}" />
        @else
            <x-ui.table>
                <x-slot:header>
                    <th>Title</th>
                    <th class="hidden lg:table-cell">Status</th>
                    <th class="hidden lg:table-cell">Location</th>
                    <th class="hidden md:table-cell">Starts</th>
                    <th>Action</th>
                </x-slot:header>
                <x-slot:body>
                    @foreach ($workshops as $workshop)
                        <tr>
                            <td class="flex items-center">
                                <img src="{{ $workshop->hero->thumbnail }}" class="max-h-12 max-w-12 -ml-2 -my-3 mr-3 inline rounded" alt="{{ $workshop->hero->title }}" />
                                <div>
                                    <a href="{{ route('admin.workshop.edit', $workshop) }}" class="whitespace-normal font-semibold text-gray-900 hover:text-primary-color">{{ $workshop->title }}</a>
                                    <div class="lg:hidden text-xs text-gray-500">{{ $workshop->getLocationName() }} ({{ $workshop->publicStatusLabel() }})</div>
                                    <div class="md:hidden text-xs text-gray-500">{{ \Carbon\Carbon::parse($workshop->starts_at)->format('j/m/Y g:i a') }}</div>
                                </div>
                            </td>
                            <td class="hidden lg:table-cell">{{ $workshop->publicStatusLabel() }}</td>
                            <td class="hidden lg:table-cell">{{ $workshop->getLocationName() }}</td>
                            <td class="hidden md:table-cell">{{ \Carbon\Carbon::parse($workshop->starts_at)->format('M j Y, g:i a') }}</td>
                            <td>
                                <div class="flex justify-center gap-3">
                                <a href="{{ route('admin.workshop.edit', $workshop) }}" class="hover:text-primary-color" title="Edit"><i class="fa-solid fa-pen-to-square"></i></a>
                                @if($workshop->registration === 'tickets')
                                    <a href="{{ route('admin.workshop.tickets', $workshop) }}" class="hover:text-primary-color" title="View tickets"><i class="fa-solid fa-ticket"></i></a>
                                @endif
                                @if($workshop->registration === 'interest' || (int) ($workshop->interests_count ?? 0) > 0)
                                    <a href="{{ route('admin.workshop.interests', $workshop) }}" class="inline-flex items-center gap-1 hover:text-primary-color" title="View interest registrations">
                                        <i class="fa-solid fa-thumbs-up"></i>
                                        <span class="text-xs font-medium">{{ number_format((int) ($workshop->interests_count ?? 0)) }} {{ \Illuminate\Support\Str::plural('interest', (int) ($workshop->interests_count ?? 0)) }}</span>
                                    </a>
                                @endif
                                <a href="{{ route('admin.workshop.attendance', $workshop) }}" class="hover:text-primary-color" title="Attendance"><i class="fa-solid fa-user-check"></i></a>
                                @if($workshop->pick_list_template_id)
                                    <a href="{{ route('admin.workshop.pick-list', $workshop) }}" class="hover:text-primary-color" title="Pick List"><i class="fa-solid fa-list-check"></i></a>
                                @else
                                    <span class="text-gray-300" title="No pick list template assigned"><i class="fa-solid fa-list-check"></i></span>
                                @endif
                                @if((bool) $workshop->is_hidden)
                                    <a href="#" class="hover:text-primary-color" title="Copy workshop link" x-data x-on:click.prevent="SM.copyToClipboard(@js(route('workshop.show', $workshop)))"><i class="fa-solid fa-link"></i></a>
                                @endif
                                <a href="{{ route('admin.workshop.duplicate', $workshop) }}" class="hover:text-primary-color" title="Duplicate"><i class="fa-regular fa-copy"></i></a>
                                <a href="#" class="hover:text-red-600" x-data x-on:click.prevent="SM.confirmDelete('{{ csrf_token() }}', 'Delete workshop?', 'Are you sure you want to delete this workshop? This action cannot be undone', '{{ route('admin.workshop.destroy', $workshop) }}')" title="Delete"><i class="fa-solid fa-trash"></i></a>
                                </div>
                            </td>
                        </tr>
                  @endforeach
                </x-slot:body>
            </x-ui.table>

            {{ $workshops->appends(request()->query())->links() }}
        @endif

    </x-container>
</x-layout>
