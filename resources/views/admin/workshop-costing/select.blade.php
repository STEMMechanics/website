<x-layout>
    <x-mast title="Workshop costings" description="Choose a pricing plan to view its workshop costings." />

    <dialog x-data x-init="$SM.nextTickShowModal($el)" x-on:cancel.prevent="$SM.navigateTo('{{ route('admin.cost-centre.allocations') }}')" class="w-full max-w-lg rounded-2xl p-0 shadow-2xl backdrop:bg-slate-900/60">
        <form method="GET" action="{{ route('admin.workshop-costing.pdf') }}" class="space-y-5 p-6">
            <div>
                <h1 class="text-xl font-semibold text-slate-900">View workshop costings</h1>
                <p class="mt-1 text-sm text-slate-600">Choose which pricing plan to use for this reference sheet.</p>
            </div>

            <x-ui.select name="version_id" label="Pricing plan" :value="$defaultVersionId" required autofocus>
                @foreach($versions as $version)
                    <option value="{{ $version->id }}" @selected((int) $version->id === $defaultVersionId)>{{ $version->name }}{{ (int) $version->id === $defaultVersionId ? ' (Default)' : '' }}</option>
                @endforeach
            </x-ui.select>

            <div class="flex flex-wrap justify-end gap-2">
                <x-ui.button color="outline" href="{{ route('admin.cost-centre.allocations') }}">Cancel</x-ui.button>
                <x-ui.button type="submit">View costings</x-ui.button>
            </div>
        </form>
    </dialog>
</x-layout>
