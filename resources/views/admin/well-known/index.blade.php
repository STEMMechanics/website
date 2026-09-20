<x-layout>
    <x-mast description="Public files for domain verification and automated services.">Verification files
        <x-slot:actions><x-ui.button color="mast" href="{{ route('admin.well-known.create') }}">Add file</x-ui.button></x-slot:actions>
    </x-mast>
    <x-container class="py-6">
        <p class="mb-5 text-sm text-gray-600">Files are published at <code>/.well-known/</code>. Existing verification files are included below.</p>
        <div class="divide-y divide-gray-200 rounded-xl border border-gray-200 bg-white">
            @forelse($files as $file)
                <div class="flex flex-wrap items-center justify-between gap-3 p-4">
                    <div class="min-w-0"><a class="break-all font-semibold text-primary-color" href="{{ route('admin.well-known.edit', $file['name']) }}">{{ $file['name'] }}</a><p class="text-sm text-gray-500">{{ number_format($file['size']) }} bytes</p></div>
                    <x-ui.button color="outline" href="{{ url('/.well-known/'.$file['name']) }}" target="_blank" rel="noopener">Open public file</x-ui.button>
                </div>
            @empty
                <p class="p-5 text-gray-500">No verification files yet.</p>
            @endforelse
        </div>
    </x-container>
</x-layout>
