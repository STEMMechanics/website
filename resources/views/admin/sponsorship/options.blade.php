<x-layout>
    <x-admin.sponsorship-mast title="Options" description="Manage the organisation-wide sponsorship checkout, recognition and support methods.">
        <x-slot:actions>
            <x-ui.button color="mast" variant="outline" data-record-editor data-record-title="Checkout settings" href="{{ route('admin.sponsorship.checkout.edit') }}">Checkout settings</x-ui.button>
            <x-ui.button color="mast" data-record-editor data-record-title="Add sponsorship amount" href="{{ route('admin.sponsorship.option.create') }}">Add sponsorship amount</x-ui.button>
        </x-slot:actions>
    </x-admin.sponsorship-mast>

    <x-container class="py-5 sm:py-8">
        @if(session('message'))<p class="mb-5 rounded-xl bg-green-50 p-4 text-sm text-green-800">{{ session('message') }}</p>@endif
        @if($errors->any())<div class="mb-5 rounded-xl bg-red-50 p-4 text-sm text-red-800">{{ $errors->first() }}</div>@endif

        <section class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm sm:p-6">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <h2 class="text-xl font-semibold text-gray-900">Sponsorship amounts</h2>
                    <p class="mt-1 max-w-3xl text-sm leading-6 text-gray-600">These options are shared across STEMMechanics sponsorships. Removing an amount archives it from checkout. Unused archived amounts can be deleted permanently; options linked to sponsorship history are kept.</p>
                </div>
            </div>
            <div id="sponsorship-options-list" data-record-refresh class="mt-5">
                @include('admin.sponsorship.partials.options-list', ['options' => $options, 'project' => $primary])
            </div>
        </section>

        <section class="mt-6 rounded-2xl border border-gray-200 bg-white p-5 shadow-sm sm:p-6">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <h2 class="text-xl font-semibold text-gray-900">Business sponsors</h2>
                    <p class="mt-1 max-w-3xl text-sm leading-6 text-gray-600">Set the public recognition groups and thresholds, such as Major Sponsors. Recognition stays private unless a sponsor opts in. Active Major Sponsors are featured on the homepage.</p>
                </div>
                <x-ui.button color="primary-outline" size="compact" data-record-editor data-record-title="Add business sponsor group" href="{{ route('admin.sponsorship.group.create') }}">Add group</x-ui.button>
            </div>
            <div id="business-sponsor-groups-list" data-record-refresh class="mt-5">
                @include('admin.sponsorship.partials.recognition-groups-list', ['levels' => $levels])
            </div>
        </section>

        <section class="mt-6 rounded-2xl border border-gray-200 bg-white p-5 shadow-sm sm:p-6">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <h2 class="text-xl font-semibold text-gray-900">Bitcoin</h2>
                    <p class="mt-1 max-w-3xl text-sm leading-6 text-gray-600">Bitcoin is an organisation-wide support method handled separately from card sponsorships. It is not processed by Square and does not create a recurring sponsorship record.</p>
                </div>
                @if($bitcoinEnabled && $bitcoinAddress)
                    <x-ui.badge color="success">Enabled</x-ui.badge>
                @else
                    <x-ui.badge color="gray">Disabled</x-ui.badge>
                @endif
            </div>

            <form method="POST" action="{{ route('admin.sponsorship.bitcoin.update') }}" class="mt-5 border-t border-gray-100 pt-5">
                @csrf
                @method('PUT')
                <div class="grid gap-x-4 md:grid-cols-2">
                    <input type="hidden" name="bitcoin_enabled" value="0">
                    <x-ui.checkbox class="mb-4" name="bitcoin_enabled" label="Show Bitcoin support on the Sponsor page" :checked="old('bitcoin_enabled', $bitcoinEnabled)" />
                    <x-ui.input class="md:col-span-2" name="bitcoin_receive_address" label="Bitcoin receive address" :value="old('bitcoin_receive_address', $bitcoinAddress)" maxlength="255" />
                    <p class="md:col-span-2 -mt-2 text-sm text-gray-500">A wallet-ready QR code is generated automatically from this address on the Sponsor page.</p>
                </div>
                <div class="mt-3 flex justify-end"><x-ui.button type="submit">Save Bitcoin settings</x-ui.button></div>
            </form>
        </section>
    </x-container>

    <x-ui.record-dialog />
</x-layout>
