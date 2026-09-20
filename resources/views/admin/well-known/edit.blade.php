<x-layout>
    <x-mast backRoute="admin.well-known.index" backTitle="Verification files">{{ $filename ? 'Edit' : 'Add' }} Verification File
        @if($filename)
            <x-slot:actions><x-ui.button color="mast" href="{{ url('/.well-known/'.$filename) }}" target="_blank" rel="noopener">Open public file</x-ui.button></x-slot:actions>
        @endif
    </x-mast>
    <x-container class="py-6">
        <form method="POST" enctype="multipart/form-data" action="{{ $filename ? route('admin.well-known.update', $filename) : route('admin.well-known.store') }}"
            x-data="{ mode: @js(old('mode', $canEditText ? 'text' : 'upload')), filename: @js(old('filename', $filename ?? '')) }">
            @csrf
            @if($filename) @method('PUT') @endif
            <x-ui.input name="filename" label="Filename" :value="$filename ?? ''" x-model="filename" :readonly="(bool) $filename" required info="Use an extensionless name, or .txt, .json or .xml. Files go directly inside /.well-known/." />
            <x-ui.select name="mode" label="File contents" x-model="mode">
                @if($canEditText)<option value="text">Enter text</option>@endif
                <option value="upload">Upload a file</option>
            </x-ui.select>
            @if($canEditText)
                <div x-show="mode === 'text'">
                    <x-ui.input type="textarea" name="verification_text" label="Text" :value="$content" rows="16" fieldClasses="font-mono text-sm" x-bind:disabled="mode !== 'text'" info="Text is published as entered. For signed verification files, upload the original to preserve its exact bytes." />
                </div>
            @endif
            <div x-show="mode === 'upload'" x-cloak>
                <x-ui.file-upload name="document" label="Verification file" x-bind:disabled="mode !== 'upload'" x-on:change="if (!filename) filename = $event.target.files[0]?.name || ''" info="Maximum 1 MB. Uploading replaces this file’s contents without changing its public URL." />
            </div>
            <x-ui.editor-actions>
                @if($filename)
                    <x-ui.button type="button" color="danger" x-on:click="SM.confirmDelete('{{ csrf_token() }}', 'Delete verification file?', 'Services relying on this file may no longer be able to verify your domain.', '{{ route('admin.well-known.destroy', $filename) }}')">Delete</x-ui.button>
                @endif
                <x-ui.button type="submit" class="ml-auto">Save file</x-ui.button>
            </x-ui.editor-actions>
        </form>
    </x-container>
</x-layout>
