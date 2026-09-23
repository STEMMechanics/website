<x-layout title="Media downloads">
    <x-mast title="Media downloads" description="Review which files are being downloaded most often.">
        <x-slot:breadcrumbs><a href="{{ route('admin.dashboard') }}" class="hover:underline">Dashboard</a></x-slot:breadcrumbs>
    </x-mast>
    <x-container class="py-5 sm:py-8">
        <form method="GET" class="mb-5 flex flex-wrap items-end gap-3 rounded-xl border border-gray-200 bg-white p-4">
            <x-ui.input type="date" name="from" label="From" :value="$from->toDateString()" class="mb-0" />
            <x-ui.input type="date" name="to" label="To" :value="$to->copy()->subDay()->toDateString()" class="mb-0" />
            <x-ui.select name="limit" label="Show" class="mb-0 min-w-32">
                @foreach([10, 25, 50, 100] as $option)
                    <option value="{{ $option }}" @selected($limit === $option)>Top {{ $option }}</option>
                @endforeach
            </x-ui.select>
            <x-ui.button type="submit">Apply</x-ui.button>
        </form>

        <div class="overflow-hidden rounded-xl border border-gray-200 bg-white">
            <x-ui.table variant="listing">
                <x-slot:header>
                    <x-ui.list-heading label="File" />
                    <x-ui.list-heading label="Type" />
                    <x-ui.list-heading class="text-right" label="Requests" />
                    <x-ui.list-heading class="hidden sm:table-cell" label="Last downloaded" />
                </x-slot:header>
                <x-slot:body>
                    @forelse($rows as $row)
                        <tr>
                            <td><a href="{{ route('admin.media.edit', ['media' => $row->media_name]) }}" class="font-semibold text-gray-900 hover:text-primary-color">{{ $row->title }}</a><div class="text-xs text-gray-500">{{ $row->media_name }}</div></td>
                            <td>{{ $row->mime_type }}</td>
                            <td class="text-right font-semibold">{{ number_format((int) $row->downloads) }}</td>
                            <td class="hidden sm:table-cell"><x-ui.date-time>{{ \Carbon\Carbon::parse($row->last_downloaded_at)->format('M j, Y g:i a') }}</x-ui.date-time></td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="text-center text-gray-500">No media downloads found for this period.</td></tr>
                    @endforelse
                </x-slot:body>
            </x-ui.table>
        </div>
    </x-container>
</x-layout>
