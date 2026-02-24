<x-layout>
    <x-mast>Site Options</x-mast>

    <x-container>
        <x-ui.toolbar>
            <x-slot:left>
                <x-ui.button type="link" href="{{ route('admin.site_option.create') }}">Create</x-ui.button>
            </x-slot:left>
            <x-slot:right>
                <x-ui.search name="search" label="Search" />
            </x-slot:right>
        </x-ui.toolbar>

        @if($siteOptions->isEmpty())
        <x-none-found item="site options" search="{{ request()->get('search') }}" />
        @else
        <x-ui.table>
            <x-slot:header>
                <th>Name</th>
                <th>Value</th>
                <th>Actions</th>
            </x-slot:header>
            <x-slot:body>
                @foreach($siteOptions as $siteOption)
                @php
                $valuePlain = str_replace(["\r\n", "\r", "\n"], ' | ', (string) $siteOption->value);
                $valuePreview = \Illuminate\Support\Str::limit($valuePlain, 220);
                @endphp
                <tr>
                    <td class="whitespace-nowrap!">{{ $siteOption->name }}</td>
                    <td class="text-left">
                        <div class="site-option-value-preview" title="{{ (string) $siteOption->value }}">
                            {{ $valuePreview !== '' ? $valuePreview : '-' }}
                        </div>
                    </td>
                    <td>
                        <div class="flex justify-center gap-3">
                            <a href="{{ route('admin.site_option.edit', $siteOption) }}" class="hover:text-primary-color"><i class="fa-solid fa-pen-to-square"></i></a>
                            <a href="#" class="hover:text-red-600" x-data x-on:click.prevent="SM.confirmDelete('{{ csrf_token() }}', 'Delete site option?', 'Are you sure you want to delete this site option?', '{{ route('admin.site_option.destroy', $siteOption) }}')"><i class="fa-solid fa-trash"></i></a>
                        </div>
                    </td>
                </tr>
                @endforeach
            </x-slot:body>
        </x-ui.table>

        {{ $siteOptions->appends(request()->query())->links() }}
        @endif
    </x-container>
</x-layout>

<style>
    .site-option-value-preview {
        /* max-width: 34rem; */
        overflow: hidden;
        text-overflow: ellipsis;
        /* white-space: nowrap; */
    }
</style>
