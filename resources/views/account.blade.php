@php
$user = auth()->user();
$linkedOrganisations = $linkedOrganisations ?? collect();
$pendingOrganisationRequests = collect($pendingOrganisationRequests ?? []);
$organisationMembershipRequests = collect($organisationMembershipRequests ?? []);
$primaryOrganisationId = (string) old('primary_organisation_id', $primaryOrganisationId ?? '');
$linkedOrganisation = $linkedOrganisations->first(fn ($organisation) => (string) $organisation->id === $primaryOrganisationId);
$primaryOrganisationIsSaved = $primaryOrganisationId !== '' && (string) $user->primary_organisation_id === $primaryOrganisationId;
$formatOrganisationAddress = static function ($organisation, string $type): string {
    if (! $organisation) {
        return '';
    }

    $stateAndPostcode = trim(implode(' ', array_filter([
        $organisation->{$type.'_state'},
        $organisation->{$type.'_postcode'},
    ], fn ($value) => filled($value))));

    return implode(', ', array_filter([
        $organisation->{$type.'_address'},
        $organisation->{$type.'_address2'},
        $organisation->{$type.'_city'},
        $stateAndPostcode,
        $organisation->{$type.'_country'},
    ], fn ($value) => filled($value)));
};
$linkedBillingAddressSummary = $formatOrganisationAddress($linkedOrganisation, 'billing');
$linkedShippingAddressSummary = $formatOrganisationAddress($linkedOrganisation, 'shipping');
$accountMastTabs = $canViewOrganisation ? [
    ['title' => 'Account', 'route' => route('account.show'), 'active' => request()->routeIs('account.show')],
    ['title' => 'Organisation', 'route' => route('account.organisation.show'), 'active' => request()->routeIs('account.organisation.show')],
] : null;
$useOrganisationBillingAddress = $linkedOrganisation && (bool) old('use_organisation_billing_address', $primaryOrganisationIsSaved && $user->use_organisation_billing_address);
$useOrganisationShippingAddress = $linkedOrganisation && (bool) old('use_organisation_shipping_address', $primaryOrganisationIsSaved && $user->use_organisation_shipping_address);

$shipping_same_billing = $user->shipping_address === $user->billing_address
    && $user->shipping_address2 === $user->billing_address2
    && $user->shipping_city === $user->billing_city
    && $user->shipping_state === $user->billing_state
    && $user->shipping_postcode === $user->billing_postcode
    && $user->shipping_country === $user->billing_country;
if (old('shipping_same_billing') !== null) {
    $shipping_same_billing = filter_var(old('shipping_same_billing'), FILTER_VALIDATE_BOOLEAN);
}
if ($useOrganisationShippingAddress) {
    $shipping_same_billing = false;
}
$groupSlugs = $user->groupSlugs();
$rememberedDevices = collect($rememberedDevices ?? []);
$currentRememberedTokenId = (string) ($currentRememberedTokenId ?? '');
$keepSignedInDeviceOld = old('keep_signed_in_device');
$keepSignedInDeviceChecked = $keepSignedInDeviceOld !== null
    ? in_array((string) $keepSignedInDeviceOld, ['1', 'on', 'true'], true)
    : ($currentRememberedTokenId !== '');
@endphp

<x-layout>
    <x-mast description="Manage your account details, addresses, subscriptions, and sign-in settings." :tabs="$accountMastTabs">Account Settings</x-mast>

    <x-container inner-class="max-w-6xl">
        <form
            method="POST"
            action="{{ route('account.update') }}"
            id="account-settings-form"
            data-account-settings-form
            data-organisation-link-endpoint="{{ route('account.organisation.link') }}"
            class="my-8"
            x-data
            x-on:submit.prevent="$SM.updateShippingAddressAndSubmit($el)"
        >
            @csrf
            <input type="hidden" name="remembered_device_hint" id="remembered_device_hint" value="" />
            <input type="hidden" name="remembered_device_touch_points" id="remembered_device_touch_points" value="" />

            <div class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_22rem]">
                <div class="space-y-6">
                    <section class="rounded-3xl border border-gray-200 bg-white p-5 shadow-sm sm:p-6">
                        <div class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
                            <div>
                                <h2 class="text-lg font-semibold text-gray-900">Profile Details</h2>
                                <p class="mt-1 text-sm text-gray-600">Update the information used for your account, invoices, and contact history.</p>
                            </div>
                        </div>

                        <div class="mt-6 grid gap-4 md:grid-cols-2">
                            <x-ui.input label="First name" name="firstname" value="{{ $user->firstname }}" required />
                            <x-ui.input label="Surname" name="surname" value="{{ $user->surname }}" />
                            <x-ui.input type="email" label="Email" name="email" value="{{ $user->email }}" info="{{ $user->email_update_pending ? 'Pending request to change to ' . $user->email_update_pending : '' }}"/>
                            <x-ui.input label="Phone" name="phone" value="{{ $user->phone }}" />
                            <div class="border-t border-gray-100 pt-5 md:col-span-2">
                                <div class="mb-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                                    <h3 class="text-sm font-semibold text-gray-900">Your organisations</h3>
                                    <x-ui.button type="button" color="outline" size="small" data-open-dialog="account-organisation-link-dialog" aria-haspopup="dialog">
                                        <i class="fa-solid fa-plus mr-2" aria-hidden="true"></i>Add or link organisation
                                    </x-ui.button>
                                </div>
                                @error('primary_organisation_id')
                                    <p class="mb-3 rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700" role="alert">{{ $message }}</p>
                                @enderror
                                <div class="space-y-2" data-account-organisation-list>
                                    @include('account.partials.organisation-list')
                                </div>
                                @error('organisation')
                                    <p class="mt-3 rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700" role="alert">{{ $message }}</p>
                                @enderror

                            </div>
                        </div>
                        @if($groupSlugs !== [])
                            <div class="mt-2 border-t border-gray-100 pt-4">
                                <div class="text-xs font-semibold uppercase tracking-wide text-gray-500">Your groups</div>
                                <div class="mt-3 flex flex-wrap gap-2">
                                    @foreach($groupSlugs as $groupSlug)
                                        <x-ui.badge color="gray">{{ $groupSlug }}</x-ui.badge>
                                    @endforeach
                                </div>
                            </div>
                        @endif
                    </section>

                    <section class="rounded-3xl border border-gray-200 bg-white p-5 shadow-sm sm:p-6">
                        <div>
                            <h2 class="text-lg font-semibold text-gray-900">Addresses</h2>
                            <p class="mt-1 text-sm text-gray-600">Keep your billing and shipping details current for orders and invoices.</p>
                        </div>

                        <x-ui.grid class="mt-6 gap-6 lg:grid-cols-2">
                            <div class="rounded-2xl bg-gray-50 p-4 flex flex-col justify-between">
                                <div>
                                    <h3 class="text-sm font-semibold text-gray-900">Billing address</h3>
                                    <div class="mt-4" data-account-organisation-address-option="billing" @if(!$linkedOrganisation) hidden @endif>
                                        <x-ui.checkbox
                                            class="mb-0"
                                            :label="'Use '.($linkedOrganisation?->name ?? 'organisation').' billing address'"
                                            name="use_organisation_billing_address"
                                            :checked="$useOrganisationBillingAddress"
                                            data-account-organisation-billing
                                            :disabled="!$linkedOrganisation"
                                        />
                                    </div>
                                    <p class="mt-2 rounded-lg bg-white px-3 py-2 text-xs text-gray-600" data-account-organisation-address-preview="billing" @if(!$useOrganisationBillingAddress) hidden @endif>{{ $linkedBillingAddressSummary !== '' ? $linkedBillingAddressSummary : 'No billing address has been saved for this organisation yet.' }}</p>
                                </div>
                                <fieldset data-account-address-fields="billing" @disabled($useOrganisationBillingAddress)>
                                <div class="mt-4">
                                    <x-ui.input label="Address" name="billing_address" value="{{ $user->billing_address }}" />
                                    <x-ui.input label="Address 2" name="billing_address2" value="{{ $user->billing_address2 }}" />
                                    <x-ui.input label="City" name="billing_city" value="{{ $user->billing_city }}" />
                                    <div class="flex flex-col sm:gap-8 sm:flex-row">
                                        <div class="flex-1">
                                            <x-ui.input label="State" name="billing_state" value="{{ $user->billing_state }}" />
                                        </div>
                                        <div class="flex-1">
                                            <x-ui.input label="Postcode" name="billing_postcode" value="{{ $user->billing_postcode }}" />
                                        </div>
                                    </div>
                                    <x-ui.input label="Country" name="billing_country" value="{{ $user->billing_country }}" />
                                </div>
                                </fieldset>
                            </div>

                            <div class="rounded-2xl bg-gray-50 p-4">
                                <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                                    <div>
                                        <h3 class="text-sm font-semibold text-gray-900">Shipping address</h3>
                                        <div class="mt-4" data-account-organisation-address-option="shipping" @if(!$linkedOrganisation) hidden @endif>
                                            <x-ui.checkbox
                                                class="mb-0"
                                                :label="'Use '.($linkedOrganisation?->name ?? 'organisation').' shipping address'"
                                                name="use_organisation_shipping_address"
                                                :checked="$useOrganisationShippingAddress"
                                                data-account-organisation-shipping
                                                :disabled="!$linkedOrganisation"
                                            />
                                        </div>
                                        <p class="mt-2 rounded-lg bg-white px-3 py-2 text-xs text-gray-600" data-account-organisation-address-preview="shipping" @if(!$useOrganisationShippingAddress) hidden @endif>{{ $linkedShippingAddressSummary !== '' ? $linkedShippingAddressSummary : 'No shipping address has been saved for this organisation yet.' }}</p>
                                        <x-ui.checkbox class="mt-4" label="Same as billing" name="shipping_same_billing" :checked="$shipping_same_billing" data-account-shipping-same-billing />
                                    </div>
                                </div>
                                <fieldset class="mt-0" data-account-address-fields="shipping" @disabled($useOrganisationShippingAddress)>
                                    <x-ui.input label="Address" name="shipping_address" value="{{ $user->shipping_address }}" readonly="{{ $shipping_same_billing }}" />
                                    <x-ui.input label="Address 2" name="shipping_address2" value="{{ $user->shipping_address2 }}" readonly="{{ $shipping_same_billing }}" />
                                    <x-ui.input label="City" name="shipping_city" value="{{ $user->shipping_city }}" readonly="{{ $shipping_same_billing }}" />
                                    <div class="flex flex-col sm:gap-8 sm:flex-row">
                                        <div class="flex-1">
                                            <x-ui.input label="State" name="shipping_state" value="{{ $user->shipping_state }}" readonly="{{ $shipping_same_billing }}" />
                                        </div>
                                        <div class="flex-1">
                                            <x-ui.input label="Postcode" name="shipping_postcode" value="{{ $user->shipping_postcode }}" readonly="{{ $shipping_same_billing }}" />
                                        </div>
                                    </div>
                                    <x-ui.input label="Country" name="shipping_country" value="{{ $user->shipping_country }}" readonly="{{ $shipping_same_billing }}" />
                                </fieldset>
                            </div>
                        </x-ui.grid>
                    </section>
                </div>

                <div class="space-y-6">
                    <section class="rounded-3xl border border-gray-200 bg-white p-5 shadow-sm sm:p-6">
                        <div class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
                            <div>
                                <h2 class="text-lg font-semibold text-gray-900">Notifications</h2>
                            </div>
                        </div>

                        <x-ui.grid class="mt-6 gap-6 lg:grid-cols-2 xl:grid-cols-1">
                            <div>
                                <div class="rounded-2xl bg-gray-50 p-4">
                                    <h3 class="text-sm font-semibold text-gray-900">Email notifications</h3>
                                    <div class="mt-4">
                                        <x-ui.checkbox label="Upcoming Workshops" name="subscribed" checked="{{ $user->subscribed }}" />
                                        @if($user->isAdmin())
                                            <x-ui.checkbox label="Workplan email" name="dashboard_email_opt_in" :checked="$user->dashboard_email_opt_in" />
                                        @endif
                                    </div>
                                </div>
                            </div>
                            @if($user->isAdmin())
                                @if(config('security.admin_mfa_required') && $user->tfa_secret === null)
                                    <p class="text-sm text-gray-600">Set up two-factor authentication to manage browser notifications.</p>
                                @else
                                    <x-push-settings :embedded="true" />
                                @endif
                            @endif
                        </x-ui.grid>
                    </section>
                </div>
            </div>

            <div class="mt-6 space-y-6">
                <section class="rounded-3xl border border-gray-200 bg-white p-5 shadow-sm sm:p-6">
                    <div class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
                        <div>
                            <h2 class="text-lg font-semibold text-gray-900">Remembered Devices</h2>
                            <p class="mt-1 text-sm text-gray-600">Review devices that can stay signed in and remove any you no longer trust.</p>
                        </div>
                        <x-ui.badge color="gray" uppercase data-remembered-devices-count>{{ $rememberedDevices->count() }} saved</x-ui.badge>
                    </div>

                    <div class="rounded-2xl mt-4 bg-gray-50 p-4">
                        <h3 class="text-sm font-semibold text-gray-900">This device</h3>
                        <p class="mt-1 text-sm text-gray-600">Control whether this browser stays signed in between visits.</p>
                        <div class="mt-4">
                            <input type="hidden" name="keep_signed_in_device" value="0" />
                            <x-ui.checkbox
 id="keep_signed_in_device"
 label="Keep me signed in on this device"
 name="keep_signed_in_device"
 checked="{{ $keepSignedInDeviceChecked }}"
 />
                        </div>
                    </div>

                    <p id="remembered-devices-empty" class="mt-5 rounded-2xl border border-dashed border-gray-300 bg-gray-50 px-4 py-6 text-sm text-gray-500 {{ $rememberedDevices->isEmpty() ? '' : 'hidden' }}">No remembered devices have been saved.</p>
                    <div id="remembered-devices-list" data-current-token-id="{{ $currentRememberedTokenId }}" class="mt-5 space-y-3 {{ $rememberedDevices->isEmpty() ? 'hidden' : '' }}">
                        @foreach($rememberedDevices as $device)
                            <div class="rounded-2xl border border-gray-200 bg-gray-50 px-4 py-4" data-device-row data-device-id="{{ $device['id'] }}">
                                @php
                                    $defaultDeviceTitle = (string) ($device['default_title'] ?? 'Browser Device');
                                    $displayDeviceTitle = (string) ($device['title'] ?? $defaultDeviceTitle);
                                @endphp
                                <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                                    <div class="min-w-0">
                                        <div class="flex flex-wrap items-center gap-2">
                                            <div class="font-semibold text-gray-900" data-device-title>{{ $displayDeviceTitle }}</div>
                                            <x-ui.button variant="plain"
                                                type="button"
                                                class="text-gray-500 hover:text-primary-color"
                                                title="Edit device name"
                                                data-device-edit
                                                data-device-id="{{ $device['id'] }}"
                                                data-device-title="{{ $displayDeviceTitle }}"
                                                data-device-nickname="{{ (string) ($device['nickname'] ?? '') }}"
                                                data-device-default-title="{{ $defaultDeviceTitle }}"
                                            >
                                                <i class="fa-solid fa-pen" aria-hidden="true"></i>
                                            </x-ui.button>
                                            @if(! empty($device['is_current']))
                                                <x-ui.badge color="success">Current device</x-ui.badge>
                                            @endif
                                        </div>
                                        <div class="mt-2 grid gap-1 text-xs text-gray-600 sm:grid-cols-2">
                                            <div>IP: {{ $device['ip_address'] ?? '-' }}</div>
                                            @if(! empty($device['browser']))
                                                <div>Browser: {{ $device['browser'] }}</div>
                                            @endif
                                            <div>Added: {{ $device['created_label'] ?? '-' }}</div>
                                            <div>Last used: {{ $device['last_used_label'] ?? '-' }}</div>
                                        </div>
                                    </div>
                                    <x-ui.button
                                        type="button"
                                        color="danger-outline"
                                        class="w-full sm:w-auto px-4! py-1.5!"
                                        data-device-remove
                                        data-device-id="{{ $device['id'] }}"
                                    >
                                        Remove
                                    </x-ui.button>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </section>
            </div>

            <x-ui.list-dialog id="account-organisation-link-dialog" title="Add or link an organisation" kind="filters" size="small">
                <div class="p-5">
                    <x-ui.field-label for="account-organisation-name" label="Organisation" class="mb-1 text-gray-700" />
                    <x-ui.input
                        class="mb-0"
                        noLabel
                        id="account-organisation-name"
                        label=""
                        name="organisation_name"
                        value=""
                        data-select-on-dialog-open
                        :suggestions="$organisationSuggestions"
                        data-organisation-names="{{ \Illuminate\Support\Js::encode($organisationNames) }}"
                        placeholder="Search or enter an organisation"
                        suggestionsTeleport="#account-organisation-link-dialog"
                        autocomplete="off"
                    />
                    <input type="hidden" name="organisation_keep_as_entered" value="0" data-organisation-keep-value>
                    <p class="mt-3 hidden rounded-lg border px-3 py-2 text-sm" data-organisation-link-status role="status"></p>
                </div>
                <div class="sm-dialog-footer flex-col items-stretch sm:flex-row sm:items-center">
                    <x-ui.button color="outline" type="button" data-close-dialog class="w-full sm:w-auto">Cancel</x-ui.button>
                    <x-ui.button type="button" class="w-full sm:w-auto" data-organisation-link-continue>Continue</x-ui.button>
                </div>
            </x-ui.list-dialog>

            <x-ui.list-dialog id="account-organisation-details-dialog" title="Add organisation details" kind="filters">
                <div class="max-h-[65dvh] overflow-y-auto p-5">
                    <p class="mb-5 text-sm text-gray-600">
                        Add what you know about <span class="font-semibold text-gray-900" data-new-organisation-name>this organisation</span>. You’ll be its first manager and can update these details later.
                    </p>
                    <div class="grid gap-x-4 sm:grid-cols-2">
                        <x-ui.select label="Organisation type" name="new_organisation_type" info="Required">
                            <option value="" selected>Select a type</option>
                            @foreach(\App\Models\Organisation::TYPES as $type => $label)
                                <option value="{{ $type }}">{{ $label }}</option>
                            @endforeach
                        </x-ui.select>
                        <x-ui.input label="Website" name="new_organisation_website_url" placeholder="example.com.au" inputmode="url" />
                    </div>
                    <p class="mb-4 hidden rounded-lg border px-3 py-2 text-sm" data-organisation-type-error role="alert"></p>
                    <p class="mb-4 hidden rounded-lg border px-3 py-2 text-sm" data-organisation-link-status role="status"></p>
                    <div class="mt-2 rounded-xl border border-gray-200 bg-gray-50 p-4">
                        <h3 class="text-sm font-semibold text-gray-900">Organisation address</h3>
                        <p class="mb-4 mt-1 text-xs text-gray-600">This starts as both the billing and shipping address. You can edit either later.</p>
                        <x-ui.input label="Address" name="new_organisation_address" />
                        <x-ui.input label="Address 2" name="new_organisation_address2" />
                        <div class="grid gap-x-4 sm:grid-cols-2">
                            <x-ui.input label="City" name="new_organisation_city" />
                            <x-ui.input label="State" name="new_organisation_state" />
                            <x-ui.input label="Postcode" name="new_organisation_postcode" />
                            <x-ui.input label="Country" name="new_organisation_country" />
                        </div>
                    </div>
                </div>
                <div class="sm-dialog-footer flex-col items-stretch sm:flex-row sm:items-center">
                    <x-ui.button type="button" color="outline" data-organisation-details-back class="w-full sm:w-auto">Back</x-ui.button>
                    <x-ui.button type="button" class="w-full sm:w-auto" data-organisation-details-submit>Save details and continue</x-ui.button>
                </div>
            </x-ui.list-dialog>
        </form>

        <div data-account-organisation-action-forms>
            @include('account.partials.organisation-action-forms')
        </div>

        @if($organisationMembershipRequests->isNotEmpty())
            <section class="mb-8 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
                <h2 class="text-lg font-semibold text-slate-900">Previous organisation requests</h2>
                <div class="mt-4 space-y-3">
                    @foreach($organisationMembershipRequests as $membershipRequest)
                        <div class="flex flex-col gap-3 rounded-xl border border-slate-200 p-4 sm:flex-row sm:items-center sm:justify-between">
                            <div>
                                <div class="font-medium text-slate-900">{{ $membershipRequest->organisation?->name ?? 'Organisation' }}</div>
                                <div class="mt-1 text-sm text-slate-600">
                                    @if($membershipRequest->status === 'approved')
                                        Your request was approved{{ $membershipRequest->reviewer ? ' by '.$membershipRequest->reviewer->getName() : ' automatically' }}.
                                    @elseif($membershipRequest->status === 'rejected')
                                        Your request was declined.
                                    @else
                                        Your request was cancelled.
                                    @endif
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </section>
        @endif

        @if($user->isAdmin() && $organisationRequestsForApproval->isNotEmpty())
            <section class="mb-8 rounded-2xl border border-amber-200 bg-amber-50/70 p-5 shadow-sm sm:p-6">
                <div class="mb-4">
                    <h2 class="text-lg font-semibold text-slate-900">Organisation requests to review</h2>
                    <p class="mt-1 text-sm text-slate-600">Linked users and administrators can approve requests. An approval links the user to the organisation.</p>
                </div>
                <div class="space-y-3">
                    @foreach($organisationRequestsForApproval as $membershipRequest)
                        <div class="flex flex-col gap-3 rounded-xl border border-amber-200 bg-white p-4 sm:flex-row sm:items-center sm:justify-between">
                            <div>
                                <div class="font-medium text-slate-900">{{ $membershipRequest->user?->getName() ?? 'User' }} requested to join {{ $membershipRequest->organisation?->name ?? 'an organisation' }}</div>
                                <div class="mt-1 text-sm text-slate-600">{{ $membershipRequest->user?->email }} · {{ $membershipRequest->created_at?->diffForHumans() }}</div>
                            </div>
                            <div class="flex flex-wrap gap-2">
                                <form method="POST" action="{{ route('account.organisation-request.review', $membershipRequest) }}">
                                    @csrf
                                    <input type="hidden" name="decision" value="approve">
                                    <x-ui.button type="submit" color="soft" size="small">Approve</x-ui.button>
                                </form>
                                <form method="POST" action="{{ route('account.organisation-request.review', $membershipRequest) }}">
                                    @csrf
                                    <input type="hidden" name="decision" value="reject">
                                    <x-ui.button type="submit" color="outline" size="small">Decline</x-ui.button>
                                </form>
                            </div>
                        </div>
                    @endforeach
                </div>
            </section>
        @endif

        <x-ui.list-dialog id="account-organisation-match-dialog" title="Check the organisation name" kind="filters" size="small">
            <div class="p-5">
                <p class="text-sm text-slate-600">Select an existing organisation to link your account, or create a new organisation with your entry.</p>
                <div class="mt-4 space-y-2" data-organisation-match-candidates></div>
            </div>
            <div class="sm-dialog-footer flex-col items-stretch sm:flex-row sm:items-center">
                <x-ui.button type="button" color="outline" data-close-dialog data-organisation-match-cancel class="w-full sm:w-auto">Cancel</x-ui.button>
                <x-ui.button type="button" data-organisation-match-keep class="w-full sm:w-auto">Create new organisation</x-ui.button>
            </div>
        </x-ui.list-dialog>
        <div class="mb-8 space-y-6">
            @include('account.partials.two-factor-card')

            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                @if($user->id !== 1)
                    <form method="POST" action="{{ route('account.destroy') }}" x-data x-on:submit.prevent="$SM.confirmAccountDelete($el)">
                        @csrf
                        @method('DELETE')
                        <x-ui.button type="submit" color="danger-outline" class="w-full sm:w-auto">Delete account</x-ui.button>
                    </form>
                @else
                    <div></div>
                @endif
                <div class="flex justify-end">
                    <x-ui.button type="submit" form="account-settings-form" class="w-full sm:w-auto">Save changes</x-ui.button>
                </div>
            </div>
        </div>
    </x-container>
</x-layout>

{{ $codes ?? '' }}

<script nonce="{{ \Illuminate\Support\Facades\Vite::cspNonce() }}">
    window.SM.whenAlpineReady(() => {
        Alpine.store('tfa', {
            show: false,
            secret: null,
            enabled: {{ $user->tfa_secret !== null ? 'true' : 'false'}},
            codes: null,
            loading: false
        });
    });

    function handleTfaImageLoad() {
        const image = document.getElementById('tfa_image');
        const loader = document.getElementById('tfa_image_loader');

        if (image) {
            image.style.display = 'block';
        }

        if (loader) {
            loader.style.display = 'none';
        }
    }

    function setupTFA() {
        document.getElementById('tfa_button').disabled = true;
        axios.get('/account/2fa')
            .then(response => {
                if(response.data.secret) {
                    Alpine.store('tfa').show = true;
                    Alpine.store('tfa').secret = response.data.secret;
                    document.getElementById('tfa_image').src = '/account/2fa/image';
                    document.getElementById('tfa_key').textContent = response.data.secret;
                } else {
                    document.getElementById('tfa_button').disabled = false;
                    SM.alert('2FA Error', 'An error occurred while setting up two-factor authentication. Please try again later', 'danger');
                }
            })
            .catch(() => {
                document.getElementById('tfa_button').disabled = false;
                SM.alert('2FA Error', 'An error occurred while setting up two-factor authentication. Please try again later', 'danger');
            });
    }

    function linkTFA() {
        Alpine.store('tfa').loading = true;
        axios.post('/account/2fa', {
            code: document.getElementById('code').value,
            secret: Alpine.store('tfa').secret,
        })
            .then(response => {
                if(response.data.success) {
                    SM.alert('2FA Linked', 'Two-factor authentication has been successfully linked to your account', 'success');
                    document.getElementById('tfa_button').disabled = false;
                    document.getElementById('code').value = '';
                    document.getElementById('tfa_image').src = '';
                    Alpine.store('tfa').show = false;
                    Alpine.store('tfa').enabled = true;
                    Alpine.store('tfa').codes = response.data.codes;
                } else {
                    SM.alert('2FA Error', 'An error occurred while linking two-factor authentication. Please try again later', 'danger');
                }
            })
            .catch(() => {
                SM.alert('2FA Error', 'An error occurred while linking two-factor authentication. Please try again later', 'danger');
            })
            .finally(() => {
                Alpine.store('tfa').loading = false;
            });
    }

    function resetBackupCodes(event) {
        event.target.classList.add('disabled');
        Alpine.store('tfa').codes = null;
        Alpine.store('tfa').loading = true;

        axios.post('/account/2fa/reset-backup-codes')
            .then(response => {
                if(response.data.success) {
                    Alpine.store('tfa').codes = response.data.codes;
                } else {
                    SM.alert('2FA Error', 'An error occurred while resetting your backup codes. Please try again later', 'danger');
                }
            })
            .catch(() => {
                SM.alert('2FA Error', 'An error occurred while resetting your backup codes. Please try again later', 'danger');
            })
            .finally(() => {
                event.target.classList.remove('disabled');
                Alpine.store('tfa').loading = false;
            });
    }

    function destroyTFA() {
        SM.confirm('Disable 2FA', 'Are you sure you want to remove two-factor authentication from your account?', 'Disable', (confirm) => {
            if(confirm) {
                axios.delete('/account/2fa')
                    .then(response => {
                        if (response.data.success) {
                            SM.alert('2FA Disabled', 'Two-factor authentication has been successfully disabled on your account', 'success');
                            Alpine.store('tfa').enabled = false;
                            Alpine.store('tfa').codes = null;
                        } else {
                            SM.alert('2FA Error', 'An error occurred while disabling two-factor authentication. Please try again later', 'danger');
                        }
                    })
                    .catch(() => {
                        SM.alert('2FA Error', 'An error occurred while disabling two-factor authentication. Please try again later', 'danger');
                    });
            }
        }, {
            confirmButtonText: 'Disable'
        });
    }

    const initRememberedDeviceActions = () => {
        const devicesList = document.getElementById('remembered-devices-list');
        const emptyState = document.getElementById('remembered-devices-empty');
        const keepSignedInCheckbox = document.getElementById('keep_signed_in_device');

        const updateEmptyState = () => {
            if (!devicesList || !emptyState) {
                return;
            }

            const remainingRows = devicesList.querySelectorAll('[data-device-row]').length;
            const count = document.querySelector('[data-remembered-devices-count]');
            if (count) count.textContent = `${remainingRows} saved`;
            const hasRows = remainingRows > 0;
            devicesList.classList.toggle('hidden', !hasRows);
            emptyState.classList.toggle('hidden', hasRows);
        };

        const handleDeviceRename = (button) => {
            if (!(button instanceof HTMLButtonElement)) {
                return;
            }

            const deviceId = String(button.dataset.deviceId || '').trim();
            const defaultTitle = String(button.dataset.deviceDefaultTitle || 'Browser Device').trim();
            const nicknameValue = String(button.dataset.deviceNickname || '').trim();
            const row = button.closest('[data-device-row]');
            const titleElement = row ? row.querySelector('[data-device-title]') : null;

            if (deviceId === '' || !(titleElement instanceof HTMLElement)) {
                return;
            }

            const saveNickname = (nicknameRaw) => {
                const nickname = String(nicknameRaw || '').trim();
                button.disabled = true;

                axios.patch(`/account/devices/${encodeURIComponent(deviceId)}/nickname`, {
                    nickname: nickname,
                }, {
                    headers: {
                        'Accept': 'application/json',
                    },
                }).then(() => {
                    button.dataset.deviceNickname = nickname;
                    titleElement.textContent = nickname !== '' ? nickname : defaultTitle;
                    SM.alert('Device updated', 'Device name saved.', 'success');
                }).catch(() => {
                    SM.alert('Update failed', 'Could not save the device name right now.', 'danger');
                }).finally(() => {
                    button.disabled = false;
                });
            };

            if (typeof Swal !== 'undefined' && Swal && typeof Swal.fire === 'function') {
                Swal.fire({
                    title: 'Edit device name',
                    input: 'text',
                    inputValue: nicknameValue,
                    inputLabel: `Leave empty to use "${defaultTitle}"`,
                    inputPlaceholder: 'Device nickname',
                    showCancelButton: true,
                    confirmButtonText: 'Save',
                    confirmButtonColor: '#0284c7',
                    cancelButtonText: 'Cancel',
                    reverseButtons: true,
                    inputAttributes: {
                        maxlength: '60',
                        autocapitalize: 'off',
                        autocorrect: 'off',
                    },
                }).then((result) => {
                    if (!result.isConfirmed) {
                        return;
                    }

                    saveNickname(result.value);
                });
                return;
            }

            const fallbackValue = window.prompt(`Edit device name (leave empty to use "${defaultTitle}")`, nicknameValue);
            if (fallbackValue === null) {
                return;
            }

            saveNickname(fallbackValue);
        };

        const handleDeviceRemove = (button) => {
            if (!(button instanceof HTMLButtonElement)) {
                return;
            }

            const deviceId = String(button.dataset.deviceId || '').trim();
            if (deviceId === '') {
                return;
            }

            const deleteRequest = async () => {
                if (button.disabled) return;
                button.disabled = true;
                const controller = new AbortController();
                const timeout = setTimeout(() => controller.abort(), 15000);

                try {
                    const response = await fetch(`/account/devices/${encodeURIComponent(deviceId)}`, {
                        method: 'DELETE',
                        credentials: 'same-origin',
                        signal: controller.signal,
                        headers: {
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                        },
                    });
                    if (!response.ok || !(await response.json())?.success) {
                        throw new Error('Device removal failed.');
                    }

                    const row = button.closest('[data-device-row]');
                    if (row instanceof HTMLElement) {
                        row.remove();
                    }

                    if (devicesList && keepSignedInCheckbox instanceof HTMLInputElement) {
                        const currentTokenId = String(devicesList.dataset.currentTokenId || '').trim();
                        if (currentTokenId !== '' && currentTokenId === deviceId) {
                            keepSignedInCheckbox.checked = false;
                            devicesList.dataset.currentTokenId = '';
                        }
                    }

                    updateEmptyState();
                    SM.alert('Device removed', 'It will need to sign in again next time.', 'success');
                } catch {
                    SM.alert('Remove failed', 'Could not remove the device right now.', 'danger');
                } finally {
                    clearTimeout(timeout);
                    button.disabled = false;
                }
            };

            if (window.SM && typeof window.SM.confirm === 'function') {
                SM.confirm('Remove device?', 'This device will no longer stay signed in.', 'Remove', (isConfirmed) => {
                    if (!isConfirmed) {
                        return;
                    }

                    return deleteRequest();
                });
            }
        };

        document.querySelectorAll('[data-device-edit]').forEach((button) => {
            button.addEventListener('click', () => {
                handleDeviceRename(button);
            });
        });

        document.querySelectorAll('[data-device-remove]').forEach((button) => {
            button.addEventListener('click', () => {
                handleDeviceRemove(button);
            });
        });

        updateEmptyState();
    };

    document.addEventListener('DOMContentLoaded', () => {
        initRememberedDeviceActions();

        const hintInput = document.getElementById('remembered_device_hint');
        const touchPointsInput = document.getElementById('remembered_device_touch_points');
        if (!hintInput || !touchPointsInput) {
            return;
        }

        const touchPoints = Number.isFinite(navigator.maxTouchPoints) ? navigator.maxTouchPoints : 0;
        touchPointsInput.value = String(Math.max(0, Math.floor(touchPoints)));

        const ua = String(navigator.userAgent || '');
        const lowerUa = ua.toLowerCase();
        const isMacintosh = lowerUa.includes('macintosh');

        if (lowerUa.includes('ipad') || (isMacintosh && touchPoints > 1)) {
            hintInput.value = 'ipad';
            return;
        }
        if (lowerUa.includes('iphone')) {
            hintInput.value = 'iphone';
            return;
        }
        if (lowerUa.includes('android')) {
            hintInput.value = 'android';
            return;
        }
        if (isMacintosh) {
            hintInput.value = 'mac';
            return;
        }
        if (lowerUa.includes('windows')) {
            hintInput.value = 'windows';
            return;
        }

        hintInput.value = 'other';
    });
</script>
