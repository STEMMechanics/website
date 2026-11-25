@if($linkedOrganisations->isNotEmpty())
    <fieldset class="space-y-2">
        <legend class="mb-2 text-sm font-medium text-gray-700">Choose your primary organisation</legend>
        @foreach($linkedOrganisations as $memberOrganisation)
            <div class="flex min-w-0 items-stretch rounded-xl border border-gray-200 bg-white transition focus-within:ring-2 focus-within:ring-primary-color/20 has-checked:border-primary-color has-checked:bg-sky-50/50">
                <label class="flex min-w-0 flex-1 cursor-pointer items-center gap-3 px-3 py-2.5">
                    <input
                        type="radio"
                        name="primary_organisation_id"
                        value="{{ $memberOrganisation->id }}"
                        data-organisation-name="{{ $memberOrganisation->name }}"
                        data-organisation-billing-address="{{ $formatOrganisationAddress($memberOrganisation, 'billing') }}"
                        data-organisation-shipping-address="{{ $formatOrganisationAddress($memberOrganisation, 'shipping') }}"
                        @checked((string) $primaryOrganisationId === (string) $memberOrganisation->id)
                        class="h-4 w-4 shrink-0 border-gray-300 text-primary-color focus:ring-primary-color"
                    >
                    <span class="min-w-0 flex-1">
                        @if($memberOrganisation->parent)
                            <span class="block truncate text-xs text-gray-500">{{ $memberOrganisation->parent->name }}</span>
                        @endif
                        <span class="block truncate text-sm font-medium text-gray-900">{{ $memberOrganisation->name }}</span>
                    </span>
                    @if($memberOrganisation->pivot?->role === 'contact')
                        <x-ui.badge size="xs" class="shrink-0 px-2 py-0.5" color="sky">Manager</x-ui.badge>
                    @endif
                </label>
                <x-ui.row-actions :menu="false" class="p-2! gap-0!">
                    <x-ui.row-action
                        :label="'View '.$memberOrganisation->name"
                        icon="fa-solid fa-arrow-up-right-from-square"
                        tone="neutral"
                        variant="plain"
                        class="text-gray-500 hover:bg-sky-50 hover:text-primary-color"
                        :href="route('account.organisation.show', ['organisation_id' => $memberOrganisation->id])"
                        target="_blank"
                        rel="noopener noreferrer"
                    />
                    <x-ui.row-action
                        :label="'Leave '.$memberOrganisation->name"
                        icon="fa-solid fa-link-slash"
                        tone="danger"
                        variant="plain"
                        class="text-gray-500 hover:bg-rose-50 hover:text-rose-700"
                        type="submit"
                        form="leave-organisation-{{ $memberOrganisation->id }}"
                    />
                </x-ui.row-actions>
            </div>
        @endforeach
        <label class="flex min-h-14 cursor-pointer items-center gap-3 rounded-xl border border-gray-200 bg-white px-3 py-2.5 text-sm text-gray-700 transition focus-within:ring-2 focus-within:ring-primary-color/20 has-checked:border-primary-color has-checked:bg-sky-50/50">
            <input type="radio" name="primary_organisation_id" value="" @checked((string) $primaryOrganisationId === '') class="h-4 w-4 shrink-0 border-gray-300 text-primary-color focus:ring-primary-color">
            <span class="text-sm">No primary organisation</span>
        </label>
    </fieldset>
@endif

@if($linkedOrganisations->isEmpty() && $pendingOrganisationRequests->isEmpty())
    <p class="rounded-xl border border-dashed border-gray-300 bg-white px-4 py-5 text-sm text-gray-600">No organisations linked.</p>
@endif

@foreach($pendingOrganisationRequests as $membershipRequest)
    <div class="flex min-w-0 items-stretch rounded-xl border border-gray-200 bg-white">
        <div class="flex min-w-0 flex-1 items-center gap-3 px-3 py-2.5">
            <span class="h-4 w-4 shrink-0" aria-hidden="true"></span>
            <span class="min-w-0 flex-1">
                @if($membershipRequest->organisation?->parent)
                    <span class="block truncate text-xs text-gray-500">{{ $membershipRequest->organisation->parent->name }}</span>
                @endif
                <span class="block truncate text-sm font-medium text-gray-900">{{ $membershipRequest->organisation?->name ?? 'Organisation' }}</span>
            </span>
            <x-ui.badge color="slate" icon="fa-solid fa-clock" size="xs" class="shrink-0 px-2 py-0.5">Pending</x-ui.badge>
        </div>
        <x-ui.row-actions :menu="false" class="p-2! gap-0!">
            <span class="size-11 shrink-0" aria-hidden="true"></span>
            <x-ui.row-action
                :label="'Cancel request to join '.($membershipRequest->organisation?->name ?? 'organisation')"
                icon="fa-solid fa-xmark"
                tone="neutral"
                variant="plain"
                class="text-gray-500 hover:bg-gray-100 hover:text-gray-900"
                type="submit"
                form="cancel-organisation-request-{{ $membershipRequest->id }}"
            />
        </x-ui.row-actions>
    </div>
@endforeach
