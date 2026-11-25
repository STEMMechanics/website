@php
$contactCount = $organisation->contacts->filter(fn ($linkedUser) => $linkedUser->pivot?->role === 'contact')->count();
$viewerId = (string) auth()->id();
$directOrganisationIds = collect($directOrganisationIds ?? [])->map(fn ($id): string => (string) $id)->all();
$inheritedOrganisationIds = collect($inheritedOrganisationIds ?? [])->map(fn ($id): string => (string) $id)->all();
$isDirectOrganisation = in_array((string) $organisation->id, $directOrganisationIds, true);
$hasInheritedManagerAccess = in_array((string) $organisation->id, $inheritedOrganisationIds, true);
@endphp

<x-layout>
    <x-mast description="Organisation details, linked people, and join requests." backRoute="account.show" backTitle="Account" :tabs="[
        ['title' => 'Account', 'route' => route('account.show'), 'active' => request()->routeIs('account.show')],
        ['title' => 'Organisation', 'route' => route('account.organisation.show'), 'active' => request()->routeIs('account.organisation.show')],
    ]">Organisation</x-mast>

    <x-container inner-class="max-w-6xl">
        @if($organisations->count() > 1)
            <form method="GET" action="{{ route('account.organisation.show') }}" data-account-organisation-switcher class="my-6 w-full">
                <x-ui.select label="Organisation" name="organisation_id" class="mb-0 w-full">
                    @foreach($organisations as $availableOrganisation)
                        @php($availableOrganisationId = (string) $availableOrganisation->id)
                        <option value="{{ $availableOrganisation->id }}" @selected((string) $availableOrganisation->id === (string) $organisation->id)>
                            {{ ($availableOrganisation->parent ? $availableOrganisation->parent->name.' — ' : '').$availableOrganisation->name }}{{ $availableOrganisationId === (string) auth()->user()->primary_organisation_id ? ' · Primary' : (in_array($availableOrganisationId, $inheritedOrganisationIds, true) ? ' · Managed via parent' : (in_array($availableOrganisationId, $directOrganisationIds, true) ? ' · Linked' : '')) }}
                        </option>
                    @endforeach
                </x-ui.select>
            </form>
        @endif
        @unless($canManageOrganisation)
            <p class="{{ $organisations->count() > 1 ? '-mt-4' : 'my-6' }} mb-6 px-2 flex items-center gap-1.5 text-xs text-slate-500" role="note">
                <i class="fa-solid fa-lock text-[11px]" aria-hidden="true"></i>
                You can view this organisation, but you don’t have permission to edit it.
            </p>
        @endunless
        <section class="my-8 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
            <div class="mb-5">
                <h2 class="text-lg font-semibold text-slate-900">{{ $organisation->name }}</h2>
                @if($hasInheritedManagerAccess)
                    <p class="mt-1 text-sm text-slate-500">You can manage this organisation through a parent organisation.</p>
                @elseif($isDirectOrganisation && !$isPrimaryOrganisation && auth()->user()->primary_organisation_id)
                    <p class="mt-1 text-sm text-slate-500">Secondary organisation</p>
                @endif
                @if($organisation->parent)
                    <p class="mt-1 text-sm text-slate-500">Managers of parent organisations can also manage this organisation.</p>
                @endif
            </div>
            <form method="POST" action="{{ route('account.organisation.update') }}" data-account-organisation-details-form>
                @csrf
                @method('PATCH')
                <input type="hidden" name="organisation_id" value="{{ $organisation->id }}">
                <fieldset @disabled(!$canManageOrganisation) aria-disabled="{{ $canManageOrganisation ? 'false' : 'true' }}" class="min-w-0 disabled:opacity-70">
                    <div class="grid gap-x-5 md:grid-cols-2">
                        <x-ui.input label="Organisation name" name="name" :value="old('name', $organisation->name)" />
                        <x-ui.input label="Website" name="website_url" :value="old('website_url', $organisation->website_url)" placeholder="https://example.com" />
                    </div>
                    <div class="grid gap-5 lg:grid-cols-2">
                        <div class="rounded-xl bg-slate-50 p-4">
                            <h3 class="mb-3 text-sm font-semibold text-slate-800">Billing address</h3>
                            <x-ui.input label="Address" name="organisation_billing_address" :value="old('organisation_billing_address', $organisation->billing_address)" />
                            <x-ui.input label="Address 2" name="organisation_billing_address2" :value="old('organisation_billing_address2', $organisation->billing_address2)" />
                            <div class="grid gap-x-4 sm:grid-cols-2">
                                <x-ui.input label="City" name="organisation_billing_city" :value="old('organisation_billing_city', $organisation->billing_city)" />
                                <x-ui.input label="State" name="organisation_billing_state" :value="old('organisation_billing_state', $organisation->billing_state)" />
                                <x-ui.input label="Postcode" name="organisation_billing_postcode" :value="old('organisation_billing_postcode', $organisation->billing_postcode)" />
                                <x-ui.input label="Country" name="organisation_billing_country" :value="old('organisation_billing_country', $organisation->billing_country)" />
                            </div>
                        </div>
                        <div class="rounded-xl bg-slate-50 p-4">
                            <div class="mb-3 flex flex-wrap items-center justify-between gap-3">
                                <h3 class="text-sm font-semibold text-slate-800">Shipping address</h3>
                                <x-ui.checkbox name="organisation_shipping_same_billing" label="Same as billing" :checked="old('organisation_shipping_same_billing', $organisation->shipping_address === $organisation->billing_address && $organisation->shipping_address2 === $organisation->billing_address2 && $organisation->shipping_city === $organisation->billing_city && $organisation->shipping_state === $organisation->billing_state && $organisation->shipping_postcode === $organisation->billing_postcode && $organisation->shipping_country === $organisation->billing_country)" />
                            </div>
                            <x-ui.input label="Address" name="organisation_shipping_address" :value="old('organisation_shipping_address', $organisation->shipping_address)" />
                            <x-ui.input label="Address 2" name="organisation_shipping_address2" :value="old('organisation_shipping_address2', $organisation->shipping_address2)" />
                            <div class="grid gap-x-4 sm:grid-cols-2">
                                <x-ui.input label="City" name="organisation_shipping_city" :value="old('organisation_shipping_city', $organisation->shipping_city)" />
                                <x-ui.input label="State" name="organisation_shipping_state" :value="old('organisation_shipping_state', $organisation->shipping_state)" />
                                <x-ui.input label="Postcode" name="organisation_shipping_postcode" :value="old('organisation_shipping_postcode', $organisation->shipping_postcode)" />
                                <x-ui.input label="Country" name="organisation_shipping_country" :value="old('organisation_shipping_country', $organisation->shipping_country)" />
                            </div>
                        </div>
                    </div>
                    <div class="mt-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                        @unless($canManageOrganisation)
                            <p class="inline-flex items-center gap-1.5 text-xs text-slate-500">
                                <i class="fa-solid fa-lock text-[11px]" aria-hidden="true"></i>
                                You don’t have permission to edit these details.
                            </p>
                        @endunless
                        <x-ui.button type="submit" color="soft" class="w-full sm:w-auto disabled:cursor-not-allowed disabled:opacity-50">Save organisation details</x-ui.button>
                    </div>
                </fieldset>
            </form>
        </section>

        <div class="mb-8 grid gap-5 lg:grid-cols-2">
        <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
            <div class="mb-4">
                <h2 class="text-lg font-semibold text-slate-900">People</h2>
                <p class="mt-1 text-sm text-slate-600">Linked members and organisation managers.</p>
            </div>
            @if($errors->has('role') || $errors->has('member'))
                <p class="mb-3 rounded-lg bg-red-50 px-3 py-2 text-sm text-red-700" role="alert">{{ $errors->first('role') ?: $errors->first('member') }}</p>
            @endif
            @if($organisation->contacts->isNotEmpty())
                <div class="space-y-3">
                    @foreach($organisation->contacts as $linkedUser)
                        <div class="flex flex-col gap-3 rounded-xl border border-slate-200 px-4 py-3 sm:flex-row sm:items-center sm:justify-between">
                            <div class="flex min-w-0 items-center gap-3">
                                <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-slate-100 text-slate-500" aria-hidden="true">
                                    <i class="fa-solid fa-user"></i>
                                </span>
                                <span class="truncate font-medium text-slate-900">{{ $linkedUser->getName() }}</span>
                            </div>
                            <div class="flex w-full min-w-0 items-center justify-end gap-2 sm:w-52 sm:shrink-0">
                                <div class="flex w-24 shrink-0 justify-center">
                                    <x-ui.badge :color="$linkedUser->pivot?->role === 'contact' ? 'sky' : 'slate'">
                                        {{ $linkedUser->pivot?->role === 'contact' ? 'Manager' : 'Member' }}
                                    </x-ui.badge>
                                </div>
                                <div class="grid w-24 shrink-0 grid-cols-2 items-center">
                                    @if($canManageOrganisation && $linkedUser->pivot?->role === 'contact' && (string) $linkedUser->id !== $viewerId && $contactCount > 1)
                                        <form method="POST" action="{{ route('account.organisation.member-role.update', $linkedUser) }}" x-data x-on:submit.prevent="$SM.confirmAndSubmit($el, {{ \Illuminate\Support\Js::encode('Remove manager access?') }}, {{ \Illuminate\Support\Js::encode($linkedUser->getName().' will remain linked as a member, but will no longer be able to manage this organisation.') }}, 'Remove access')">
                                            @csrf
                                            @method('PATCH')
                                            <input type="hidden" name="organisation_id" value="{{ $organisation->id }}">
                                            <input type="hidden" name="role" value="member">
                                            <x-ui.row-action type="submit" label="Remove manager access for {{ $linkedUser->getName() }}" icon="fa-user-minus" variant="plain" tone="danger" class="rounded-xl text-slate-500 hover:bg-rose-50 hover:text-rose-700" />
                                        </form>
                                    @elseif($canManageOrganisation && $linkedUser->pivot?->role !== 'contact')
                                        <form method="POST" action="{{ route('account.organisation.member-role.update', $linkedUser) }}" x-data x-on:submit.prevent="$SM.confirmAndSubmit($el, {{ \Illuminate\Support\Js::encode('Make this user an organisation manager?') }}, {{ \Illuminate\Support\Js::encode($linkedUser->getName().' will be able to manage organisation details, linked people, and join requests.') }}, 'Make manager')">
                                            @csrf
                                            @method('PATCH')
                                            <input type="hidden" name="organisation_id" value="{{ $organisation->id }}">
                                            <input type="hidden" name="role" value="contact">
                                            <x-ui.row-action type="submit" label="Make {{ $linkedUser->getName() }} a manager" icon="fa-user-plus" variant="plain" tone="neutral" class="rounded-xl text-slate-500 hover:bg-slate-100 hover:text-slate-800" />
                                        </form>
                                    @else
                                        <span class="size-11" aria-hidden="true"></span>
                                    @endif
                                    @if($canManageOrganisation && $linkedUser->pivot?->role !== 'contact')
                                        <form method="POST" action="{{ route('account.organisation.member.remove', $linkedUser) }}" x-data x-on:submit.prevent="$SM.confirmAndSubmit($el, {{ \Illuminate\Support\Js::encode('Remove member from organisation?') }}, {{ \Illuminate\Support\Js::encode($linkedUser->getName().' will no longer be linked to '.$organisation->name.'. Their personal account and address will remain unchanged.') }}, 'Remove member')">
                                            @csrf
                                            @method('DELETE')
                                            <input type="hidden" name="organisation_id" value="{{ $organisation->id }}">
                                            <x-ui.row-action type="submit" label="Remove {{ $linkedUser->getName() }} from {{ $organisation->name }}" icon="fa-link-slash" variant="plain" tone="danger" class="rounded-xl text-slate-500 hover:bg-rose-50 hover:text-rose-700" />
                                        </form>
                                    @else
                                        <span class="size-11" aria-hidden="true"></span>
                                    @endif
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            @else
                <div class="rounded-xl border border-dashed border-slate-300 bg-slate-50 px-4 py-6 text-center text-sm text-slate-500">
                    No linked users.
                </div>
            @endif
        </section>

        @if($canManageOrganisation)
            <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
                <div class="mb-4">
                    <h2 class="text-lg font-semibold text-slate-900">Join requests</h2>
                    <p class="mt-1 text-sm text-slate-600">Organisation managers and STEMMechanics staff can approve or decline requests.</p>
                </div>
                @if($organisationRequestsForApproval->isNotEmpty())
                    <div class="space-y-3">
                        @foreach($organisationRequestsForApproval as $membershipRequest)
                            <div class="flex flex-col gap-3 rounded-xl border border-amber-200 bg-amber-50/70 p-4 sm:flex-row sm:items-center sm:justify-between">
                                <div>
                                    <div class="font-medium text-slate-900">{{ $membershipRequest->user?->getName() ?? 'User' }} requested to join</div>
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
                @else
                    <div class="rounded-xl border border-dashed border-slate-300 bg-slate-50 px-4 py-6 text-center text-sm text-slate-500">
                        No pending join requests.
                    </div>
                @endif
            </section>
        @endif
        </div>
    </x-container>
</x-layout>
