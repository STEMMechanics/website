@php
    $editing = isset($organisation);
    $activeTab = $editing && request()->query('tab') === 'users' ? 'users' : 'details';
    $organisationTabs = $editing ? [
        ['title' => 'Details', 'route' => route('admin.organisation.edit', $organisation), 'active' => $activeTab === 'details'],
        [
            'title' => 'Linked users',
            'route' => route('admin.organisation.edit', ['organisation' => $organisation, 'tab' => 'users']),
            'active' => $activeTab === 'users',
        ],
    ] : null;
    $initialContacts = $editing
        ? $organisation->contacts->filter(fn ($contact) => $contact->pivot?->role === 'contact')->map(fn ($contact) => [
            'id' => (string) $contact->id,
            'name' => $contact->getName(),
            'email' => (string) $contact->email,
            'organisation_name' => (string) ($contact->primaryOrganisation?->name ?? ''),
            'edit_url' => route('admin.user.edit', $contact),
        ])->values()->all()
        : [];
    $shippingSameBilling = old('shipping_same_billing', $editing
        && $organisation->shipping_address === $organisation->billing_address
        && $organisation->shipping_address2 === $organisation->billing_address2
        && $organisation->shipping_city === $organisation->billing_city
        && $organisation->shipping_state === $organisation->billing_state
        && $organisation->shipping_postcode === $organisation->billing_postcode
        && $organisation->shipping_country === $organisation->billing_country);
    $autoApprovedEmailDomainsText = old(
        'auto_approved_email_domains_text',
        implode(' ', $organisation->auto_approved_email_domains ?? []),
    );
    $parentOrganisationId = (string) old('parent_id', $organisation->parent_id ?? '');
    $selectedParentOrganisation = $organisations->firstWhere('id', $parentOrganisationId);
    $parentOrganisationLabel = $selectedParentOrganisation
        ? (($selectedParentOrganisation->parent ? $selectedParentOrganisation->parent->name.' — ' : '').$selectedParentOrganisation->name)
        : '';
@endphp

<x-layout>
    <x-mast backRoute="admin.organisation.index" backTitle="Organisations" :tabs="$organisationTabs">{{ $editing ? 'Edit' : 'Create' }} Organisation</x-mast>

    <x-container>
        @if($editing)
            <x-ui.toolbar>
                <x-slot:right>
                    @if($activeTab !== 'users' && $organisationRequestsForApproval->isNotEmpty())
                        <x-ui.button color="outline" href="{{ route('admin.organisation.edit', ['organisation' => $organisation, 'tab' => 'users']) }}">
                            <i class="fa-solid fa-user-clock mr-2" aria-hidden="true"></i>Review pending requests
                        </x-ui.button>
                    @endif
                    <x-ui.button color="outline" href="{{ route('admin.workshop.history', ['organisation_id' => $organisation->id, 'include_children' => 1]) }}">
                        <i class="fa-solid fa-clock-rotate-left mr-2" aria-hidden="true"></i>Workshop History
                    </x-ui.button>
                </x-slot:right>
            </x-ui.toolbar>
        @endif

        @if($editing && $activeTab === 'users')
            <section class="my-6 rounded-xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
                <div class="mb-5">
                    <h2 class="text-lg font-semibold text-slate-900">Pending requests</h2>
                    <p class="mt-1 text-sm text-slate-600">Review requests to join {{ $organisation->name }}.</p>
                </div>
                @if($organisationRequestsForApproval->isNotEmpty())
                    <x-ui.table variant="listing" mobileCards>
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Email</th>
                                <th>Requested</th>
                                <th class="text-center"><span class="sr-only">Actions</span></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($organisationRequestsForApproval as $membershipRequest)
                                <tr>
                                    <td data-mobile-primary>
                                        <a href="{{ route('admin.user.edit', $membershipRequest->user) }}" class="font-semibold text-gray-900 hover:text-primary-color">{{ $membershipRequest->user?->getName() ?? 'User' }}</a>
                                    </td>
                                    <td data-label="Email"><a href="mailto:{{ $membershipRequest->user?->email }}" class="text-primary-color hover:underline">{{ $membershipRequest->user?->email }}</a></td>
                                    <td data-label="Requested">{{ $membershipRequest->created_at?->diffForHumans() ?? '—' }}</td>
                                    <td data-mobile-actions class="text-center">
                                        <div class="flex flex-wrap justify-end gap-2">
                                            <form method="POST" action="{{ route('account.organisation-request.review', $membershipRequest) }}">
                                                @csrf
                                                <input type="hidden" name="decision" value="approve">
                                                <input type="hidden" name="return_to_admin_organisation" value="1">
                                                <x-ui.button type="submit" color="soft" size="small">Approve</x-ui.button>
                                            </form>
                                            <form method="POST" action="{{ route('account.organisation-request.review', $membershipRequest) }}">
                                                @csrf
                                                <input type="hidden" name="decision" value="reject">
                                                <input type="hidden" name="return_to_admin_organisation" value="1">
                                                <x-ui.button type="submit" color="outline" size="small">Decline</x-ui.button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </x-ui.table>
                @else
                    <p class="rounded-xl border border-dashed border-slate-300 bg-slate-50 px-4 py-5 text-sm text-slate-600">No pending requests.</p>
                @endif
            </section>

            <section class="my-6 rounded-xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
                <div class="mb-5">
                    <h2 class="text-lg font-semibold text-slate-900">Linked users</h2>
                    <p class="mt-1 text-sm text-slate-600">People linked to {{ $organisation->name }} and their access level.</p>
                </div>
                @if($organisation->contacts->isNotEmpty())
                    <x-ui.table variant="listing" mobileCards>
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Email</th>
                                <th class="text-center">Access</th>
                                <th class="text-center"><span class="sr-only">Actions</span></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($organisation->contacts as $linkedUser)
                                <tr>
                                    <td data-mobile-primary>
                                        <a href="{{ route('admin.user.edit', $linkedUser) }}" class="font-semibold text-gray-900 hover:text-primary-color">{{ $linkedUser->getName() }}</a>
                                    </td>
                                    <td data-label="Email"><a href="mailto:{{ $linkedUser->email }}" class="text-primary-color hover:underline">{{ $linkedUser->email }}</a></td>
                                    <td data-label="Access" class="text-center">
                                        <x-ui.badge :color="$linkedUser->pivot?->role === 'contact' ? 'sky' : 'slate'">
                                            {{ $linkedUser->pivot?->role === 'contact' ? 'Manager' : 'Member' }}
                                        </x-ui.badge>
                                    </td>
                                    <td data-mobile-actions class="text-center whitespace-nowrap">
                                        <x-ui.row-actions :menu="false">
                                            <x-ui.row-action label="Edit user" icon="fa-solid fa-pen-to-square" tone="primary" href="{{ route('admin.user.edit', $linkedUser) }}" />
                                        </x-ui.row-actions>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </x-ui.table>
                @else
                    <x-none-found item="linked users" search="" title="No linked users" message="Users will appear here when they join this organisation." />
                @endif
            </section>
        @else
        <form
            method="POST"
            enctype="multipart/form-data"
            action="{{ $editing ? route('admin.organisation.update', $organisation) : route('admin.organisation.store') }}"
            x-data="smCspAdminOrganisationEdit1"
        >
            @csrf
            @if($editing) @method('PUT') @endif

            <div class="grid gap-4 md:grid-cols-2">
                <x-ui.input label="Name" name="name" value="{{ old('name', $organisation->name ?? '') }}" />
                <x-ui.select label="Type" name="type">
                    <option value="">Select type</option>
                    @foreach(\App\Models\Organisation::TYPES as $value => $label)
                        <option value="{{ $value }}" @selected(old('type', $organisation->type ?? '') === $value)>{{ $label }}</option>
                    @endforeach
                </x-ui.select>
            </div>

            <div class="mb-4 rounded-xl border border-gray-200 bg-white p-4">
                <div class="mb-3">
                    <h3 class="font-semibold text-gray-900">Organisation profile</h3>
                    <p class="text-xs text-gray-500">These details are shared with sponsorship records and can be used for public recognition when permission is given.</p>
                </div>
                <div class="grid gap-x-4 md:grid-cols-2">
                    <x-ui.input label="Website" name="website_url" type="text" inputmode="url" placeholder="example.com.au" value="{{ old('website_url', $organisation->website_url ?? '') }}" />
                    <x-ui.input
                        label="Auto-approved email domains"
                        name="auto_approved_email_domains_text"
                        :value="$autoApprovedEmailDomainsText"
                        info="Verified accounts at these exact domains can join without approval. Separate domains with spaces."
                    />
                    <x-ui.input label="ABN (optional)" name="abn" value="{{ old('abn', $organisation->abn ?? '') }}" maxlength="20" inputmode="numeric" />
                    <x-ui.input class="md:col-span-2" label="Overseas tax ID (optional)" name="foreign_tax_id" value="{{ old('foreign_tax_id', $organisation->foreign_tax_id ?? '') }}" maxlength="100" />
                    @if($editing && $organisation->logo_path)
                        <div class="mb-4 flex items-center gap-3 md:col-span-2">
                            <img src="{{ route('admin.organisation.sponsorship-logo', $organisation) }}" class="h-14 w-14 rounded-lg border border-gray-200 bg-white object-contain p-1" alt="Organisation logo">
                            <span class="text-sm text-gray-600">Current organisation logo</span>
                        </div>
                    @endif
                    <x-ui.input class="md:col-span-2" label="Organisation logo (optional)" name="logo" type="file" accept="image/png,image/jpeg,image/webp" info="Supported formats: PNG, JPEG or WebP." />
                </div>
            </div>

            <div class="mb-4 grid items-start gap-4 md:grid-cols-2">
                <div class="relative" x-on:click.outside="closeParentSearch()">
                    <label for="parent_organisation_search" class="mb-1 block pl-1 text-sm font-medium text-gray-700">Parent organisation</label>
                    <input
                        id="parent_organisation_search"
                        type="text"
                        x-model="parentSearch"
                        x-on:input="parentSearchChanged()"
                        x-on:focus="openParentSuggestions()"
                        x-on:keydown.arrow-down.prevent="moveParentSuggestion(1)"
                        x-on:keydown.arrow-up.prevent="moveParentSuggestion(-1)"
                        x-on:keydown.enter="chooseParentSuggestion($event)"
                        x-on:keydown.escape.prevent="closeParentSearch()"
                        autocomplete="off"
                        placeholder="Search organisations or leave blank"
                        role="combobox"
                        aria-autocomplete="list"
                        aria-controls="parent_organisation_results"
                        x-bind:aria-expanded="parentOpen && parentSearch.trim().length >= 2"
                        x-bind:aria-activedescendant="parentResults[parentHighlightedIndex] ? 'parent_organisation_option_' + parentResults[parentHighlightedIndex].id : null"
                        class="block w-full rounded-lg border border-gray-300 bg-white px-2.5 py-2.5 pr-10 text-sm text-gray-900 focus:border-indigo-300 focus:outline-none focus:ring-0"
                    >
                    <input type="hidden" name="parent_id" value="{{ $parentOrganisationId }}" x-bind:value="parentSelectedId">
                    <button
                        type="button"
                        x-show="parentSelectedId"
                        x-on:click.prevent="clearParentOrganisation()"
                        class="absolute right-2 top-7 inline-flex h-8 w-8 items-center justify-center rounded-md text-gray-500 hover:bg-gray-100 hover:text-gray-800 focus:outline-none focus:ring-2 focus:ring-indigo-300"
                        aria-label="Clear parent organisation"
                        title="Clear parent organisation"
                    ><i class="fa-solid fa-xmark" aria-hidden="true"></i></button>
                    <p class="mt-1 pl-1 text-xs text-gray-500">Use this for a service or branch belonging to a larger organisation.</p>
                    @error('parent_id')
                        <p class="mt-1 pl-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                    <div id="parent_organisation_results" role="listbox" aria-label="Parent organisation suggestions" class="absolute z-40 mt-1 max-h-72 w-full overflow-y-auto rounded-lg border border-gray-200 bg-white p-1 shadow-lg" x-show="parentOpen && parentSearch.trim().length >= 2" x-cloak>
                        <template x-for="(parentOrganisation, index) in parentResults" :key="parentOrganisation.id">
                            <button
                                type="button"
                                x-bind:id="'parent_organisation_option_' + parentOrganisation.id"
                                x-on:mouseenter="parentHighlightedIndex = index"
                                x-on:mousedown.prevent
                                x-on:click="selectParentOrganisation(parentOrganisation)"
                                x-bind:aria-selected="parentHighlightedIndex === index"
                                x-bind:class="parentHighlightedIndex === index ? 'bg-sky-50' : ''"
                                class="block w-full rounded-md px-3 py-2 text-left text-sm text-gray-900 hover:bg-sky-50"
                                role="option"
                                x-text="parentOrganisation.label"
                            ></button>
                        </template>
                        <p x-show="parentSearching" class="px-3 py-2 text-sm text-gray-500">Searching organisations…</p>
                        <p x-show="!parentSearching && parentResults.length === 0" class="px-3 py-2 text-sm text-gray-500">No matching organisations found.</p>
                    </div>
                </div>

                <x-ui.select label="Account Terms" name="account_terms_days" info="Default payment terms that linked users can inherit.">
                    @foreach(\App\Models\User::accountTermsOptions() as $days => $label)
                        <option value="{{ $days }}" @selected((int) old('account_terms_days', $organisation->account_terms_days ?? 0) === (int) $days)>{{ $label }}</option>
                    @endforeach
                </x-ui.select>
            </div>

            <div class="mb-4 rounded-xl border border-gray-200 bg-white p-4">
                <div class="mb-3">
                    <h3 class="font-semibold text-gray-900">Billing Address</h3>
                    <p class="text-xs text-gray-500">Used on invoices and quotes when a linked user uses the organisation billing address.</p>
                </div>
                <x-ui.input label="Address" name="billing_address" value="{{ old('billing_address', $organisation->billing_address ?? '') }}" />
                <x-ui.input label="Address 2" name="billing_address2" value="{{ old('billing_address2', $organisation->billing_address2 ?? '') }}" />
                <x-ui.input label="City" name="billing_city" value="{{ old('billing_city', $organisation->billing_city ?? '') }}" />
                <div class="grid gap-4 sm:grid-cols-2">
                    <x-ui.input label="State" name="billing_state" value="{{ old('billing_state', $organisation->billing_state ?? '') }}" />
                    <x-ui.input label="Postcode" name="billing_postcode" value="{{ old('billing_postcode', $organisation->billing_postcode ?? '') }}" />
                </div>
                <x-ui.input label="Country" name="billing_country" value="{{ old('billing_country', $organisation->billing_country ?? '') }}" />
            </div>

            <div class="mb-4 rounded-xl border border-gray-200 bg-white p-4">
                <div class="mb-3">
                    <h3 class="font-semibold text-gray-900">Shipping Address</h3>
                    <p class="text-xs text-gray-500">Used for deliveries when a linked user uses the organisation shipping address.</p>
                </div>
                <x-ui.checkbox label="Same as billing address" name="shipping_same_billing" :checked="$shippingSameBilling" x-model="shippingSameBilling" x-on:change="$SM.updateShippingAddress()" />
                <div x-show="!shippingSameBilling" x-cloak>
                <x-ui.input label="Address" name="shipping_address" value="{{ old('shipping_address', $organisation->shipping_address ?? '') }}" />
                <x-ui.input label="Address 2" name="shipping_address2" value="{{ old('shipping_address2', $organisation->shipping_address2 ?? '') }}" />
                <x-ui.input label="City" name="shipping_city" value="{{ old('shipping_city', $organisation->shipping_city ?? '') }}" />
                <div class="grid gap-4 sm:grid-cols-2">
                    <x-ui.input label="State" name="shipping_state" value="{{ old('shipping_state', $organisation->shipping_state ?? '') }}" />
                    <x-ui.input label="Postcode" name="shipping_postcode" value="{{ old('shipping_postcode', $organisation->shipping_postcode ?? '') }}" />
                </div>
                <x-ui.input label="Country" name="shipping_country" value="{{ old('shipping_country', $organisation->shipping_country ?? '') }}" />
                </div>
            </div>

            @php
                $invoiceContactEmailPlaceholder = '{'.'{email}'.'}';
                $invoiceEmailToInfo = "Separate multiple addresses with commas. Use {$invoiceContactEmailPlaceholder} for the linked invoice contact.";
                $invoiceEmailDefaultsOpen = $errors->hasAny(['invoice_email_to', 'invoice_email_cc', 'invoice_email_subject', 'invoice_email_message']);
            @endphp
            <x-ui.collapsible-section
                class="mb-4"
                title="Invoice Email Defaults"
                subtitle="Customise invoice recipients and email content for this organisation"
                :open="$invoiceEmailDefaultsOpen"
            >
                <p class="mb-4 text-xs text-gray-500">These fields start with the site defaults. Changes saved here are used for this organisation unless an invoice has its own saved email template.</p>
                <x-ui.input label="To" name="invoice_email_to" :value="old('invoice_email_to', $organisation?->invoice_email_to ?: $invoiceEmailSiteDefaults['recipient_emails'])" :info="$invoiceEmailToInfo" />
                <x-ui.input label="CC" name="invoice_email_cc" :value="old('invoice_email_cc', $organisation?->invoice_email_cc ?: $invoiceEmailSiteDefaults['cc_emails'])" info="Optional. Separate multiple addresses with commas." />
                <x-ui.input label="Subject" name="invoice_email_subject" :value="old('invoice_email_subject', $organisation?->invoice_email_subject ?: $invoiceEmailSiteDefaults['subject_line'])" />
                <x-ui.input type="textarea" label="Message" name="invoice_email_message" :value="old('invoice_email_message', $organisation?->invoice_email_message ?: $invoiceEmailSiteDefaults['email_message'])" />
                <p class="text-xs text-gray-500">Available placeholders: <code>@{{name}}</code>, <code>@{{id}}</code>, <code>@{{total}}</code>, <code>@{{outstanding}}</code>, <code>@{{due}}</code>, <code>@{{po}}</code>, <code>@{{email}}</code>, and <code>@{{pay}}</code> in the message.</p>
            </x-ui.collapsible-section>

            <x-ui.input type="textarea" label="Notes" name="notes" value="{{ old('notes', $organisation->notes ?? '') }}" />

            @unless($editing)
            <div class="mb-4 rounded-xl border border-gray-200 bg-white p-4">
                <div class="mb-3">
                    <h3 class="font-semibold text-gray-900">Initial organisation manager</h3>
                    <p class="text-xs text-gray-500">Choose a user who will be linked to the organisation and can manage its details.</p>
                </div>

                <div class="mb-4 overflow-x-auto rounded-lg border border-gray-200" x-show="contacts.length > 0">
                    <x-ui.table table-class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead class="bg-gray-50 text-left text-xs font-semibold uppercase tracking-wide text-gray-600">
                            <tr>
                                <th class="px-3 py-2">Organisation manager</th>
                                <th class="px-3 py-2">Email</th>
                                <th class="hidden px-3 py-2 md:table-cell">Organisation</th>
                                <th class="w-12 px-3 py-2"><span class="sr-only">Action</span></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <template x-for="(contact, index) in contacts" :key="contact.id">
                                <tr>
                                    <td class="px-3 py-2">
                                        <input type="hidden" x-bind:name="('contact_ids[' + (index) + ']')" :value="contact.id">
                                        <a :href="contact.edit_url" class="text-primary-color hover:underline" x-text="contact.name"></a>
                                    </td>
                                    <td class="px-3 py-2 text-gray-600" x-text="contact.email"></td>
                                    <td class="hidden px-3 py-2 text-gray-600 md:table-cell" x-text="contact.organisation_name || '-'"></td>
                                    <td class="px-3 py-2 text-center">
                                        <x-ui.button variant="plain" type="button" class="text-gray-500 hover:text-red-600" x-on:click.prevent="remove(contact.id)" title="Remove manager access" aria-label="Remove manager access">
                                            <i class="fa-solid fa-link-slash" aria-hidden="true"></i>
                                        </x-ui.button>
                                    </td>
                                </tr>
                            </template>
                        </tbody>
                    </x-ui.table>
                </div>

                <div class="mb-4 rounded-lg border border-dashed border-gray-200 bg-gray-50 px-3 py-3 text-sm text-gray-500" x-show="contacts.length === 0" x-cloak>
                    No organisation managers are designated.
                </div>

                <x-ui.input
                    id="organisation_contact_search"
                    type="search"
                    name=""
                    label="Find a user to make an organisation manager"
                    x-model="search"
                    x-on:input.debounce.350ms="findContacts()"
                    placeholder="Search name, email, or organisation"
                    class="mb-3"
                />

                <div class="rounded-lg border border-dashed border-gray-200 bg-gray-50 px-3 py-3 text-sm text-gray-500" x-show="search.trim().length < 2" x-cloak>
                    Enter at least 2 characters to search users.
                </div>
                <div class="rounded-lg border border-dashed border-gray-200 bg-gray-50 px-3 py-3 text-sm text-gray-500" x-show="searching" x-cloak>
                    Searching users…
                </div>
                <div class="max-h-72 overflow-y-auto rounded-lg border border-gray-200 bg-gray-50" x-show="search.trim().length >= 2 && !searching" x-cloak>
                    <template x-for="contact in results" :key="contact.id">
                        <x-ui.button variant="plain" type="button" class="flex w-full items-start gap-3 border-b border-gray-200 bg-white px-3 py-2 text-left text-sm last:border-b-0 hover:bg-sky-50" x-on:click.prevent="add(contact)">
                            <i class="fa-solid fa-plus mt-1 text-primary-color" aria-hidden="true"></i>
                            <span class="min-w-0">
                                <span class="block text-gray-900" x-text="contact.name"></span>
                                <span class="block text-xs text-gray-500" x-text="('' + (contact.email) + '' + (contact.organisation_name ? (' · ' + (contact.organisation_name) + '') : '') + '')"></span>
                            </span>
                        </x-ui.button>
                    </template>
                    <div class="px-3 py-3 text-sm text-gray-500" x-show="results.length === 0" x-cloak>No matching users found.</div>
                </div>
                <p class="mt-1 text-xs text-gray-500">Results are limited to 25 matches.</p>
            </div>
            @endunless

            @if($editing && $organisation->children->isNotEmpty())
                <div class="mb-4 flex flex-wrap items-center gap-2">
                    <span class="text-sm font-semibold text-gray-700">Child organisations:</span>
                    @foreach($organisation->children as $child)
                        <x-ui.button type="button" color="outline" href="{{ route('admin.organisation.edit', $child) }}">{{ $child->name }}</x-ui.button>
                    @endforeach
                </div>
            @endif

            <x-ui.editor-actions>
                @if($editing)
                    <x-ui.button data-editor-delete type="button" color="danger" x-data x-on:click.prevent="$SM.confirmDelete('{{ csrf_token() }}', 'Delete organisation?', 'Workshops and linked users will be retained but unlinked from this organisation.', '{{ route('admin.organisation.destroy', $organisation) }}')">Delete</x-ui.button>
                @endif
                <x-ui.button type="submit">Save</x-ui.button>
            </x-ui.editor-actions>
        </form>
        @endif
    </x-container>

<script nonce="{{ \Illuminate\Support\Facades\Vite::cspNonce() }}">
window.SM.whenAlpineReady(function () {
    Alpine.data('smCspAdminOrganisationEdit1', () => ({
                contacts: @js($initialContacts),
                results: [],
                search: '',
                searching: false,
                parentSelectedId: @js($parentOrganisationId),
                parentSearch: @js($parentOrganisationLabel),
                parentResults: [],
                parentSearching: false,
                parentOpen: false,
                parentHighlightedIndex: 0,
                parentSearchSequence: 0,
                parentSearchTimer: null,
                shippingSameBilling: @js((bool) $shippingSameBilling),
                searchSequence: 0,
                parentSearchChanged() {
                    this.parentSelectedId = '';
                    this.parentResults = [];
                    this.parentHighlightedIndex = 0;
                    this.parentOpen = true;
                    const term = this.parentSearch.trim();
                    const sequence = ++this.parentSearchSequence;
                    if (this.parentSearchTimer) clearTimeout(this.parentSearchTimer);
                    if (term.length < 2) {
                        this.parentSearching = false;
                        return;
                    }
                    this.parentSearching = true;
                    this.parentSearchTimer = setTimeout(() => this.findParentOrganisations(term, sequence), 250);
                },
                openParentSuggestions() {
                    this.parentOpen = !this.parentSelectedId && this.parentSearch.trim().length >= 2;
                },
                closeParentSearch() {
                    this.parentOpen = false;
                },
                clearParentOrganisation() {
                    if (this.parentSearchTimer) clearTimeout(this.parentSearchTimer);
                    this.parentSearchSequence++;
                    this.parentSelectedId = '';
                    this.parentSearch = '';
                    this.parentResults = [];
                    this.parentSearching = false;
                    this.parentOpen = false;
                    this.parentHighlightedIndex = 0;
                },
                moveParentSuggestion(step) {
                    if (!this.parentOpen || this.parentResults.length === 0) return;
                    const current = this.parentHighlightedIndex < 0 ? 0 : this.parentHighlightedIndex;
                    this.parentHighlightedIndex = (current + step + this.parentResults.length) % this.parentResults.length;
                },
                chooseParentSuggestion(event) {
                    if (!this.parentOpen || this.parentResults.length === 0) return;
                    event.preventDefault();
                    const organisation = this.parentResults[this.parentHighlightedIndex];
                    if (organisation) this.selectParentOrganisation(organisation);
                },
                selectParentOrganisation(organisation) {
                    if (!organisation) return;
                    if (this.parentSearchTimer) clearTimeout(this.parentSearchTimer);
                    this.parentSearchSequence++;
                    this.parentSelectedId = String(organisation.id);
                    this.parentSearch = organisation.label;
                    this.parentResults = [];
                    this.parentSearching = false;
                    this.parentOpen = false;
                    this.parentHighlightedIndex = 0;
                },
                async findParentOrganisations(term, sequence) {
                    try {
                        const url = new URL(@js(route('admin.organisation.options')), window.location.origin);
                        url.searchParams.set('search', term);
                        @if($editing)
                            url.searchParams.set('exclude_id', @js((string) $organisation->id));
                        @endif
                        const response = await fetch(url, { headers: { Accept: 'application/json' } });
                        if (!response.ok) throw new Error('Unable to search organisations.');
                        const data = await response.json();
                        if (sequence === this.parentSearchSequence) {
                            this.parentResults = data.organisations || [];
                            this.parentHighlightedIndex = 0;
                        }
                    } catch (error) {
                        if (sequence === this.parentSearchSequence) this.parentResults = [];
                    } finally {
                        if (sequence === this.parentSearchSequence) this.parentSearching = false;
                    }
                },
                isLinked(id) {
                    return this.contacts.some((contact) => contact.id === String(id));
                },
                add(contact) {
                    if (!this.isLinked(contact.id)) this.contacts.push(contact);
                    this.results = this.results.filter((result) => result.id !== contact.id);
                    this.search = '';
                },
                remove(id) {
                    this.contacts = this.contacts.filter((contact) => contact.id !== String(id));
                },
                async findContacts() {
                    const term = this.search.trim();
                    const sequence = ++this.searchSequence;
                    if (term.length < 2) {
                        this.results = [];
                        this.searching = false;
                        return;
                    }
                    this.searching = true;
                    try {
                        const url = new URL(@js(route('admin.organisation.contact-options')), window.location.origin);
                        url.searchParams.set('search', term);
                        const response = await fetch(url, { headers: { Accept: 'application/json' } });
                        if (!response.ok) throw new Error('Unable to search contacts.');
                        const data = await response.json();
                        if (sequence === this.searchSequence) {
                            this.results = (data.users || []).filter((contact) => !this.isLinked(contact.id));
                        }
                    } catch (error) {
                        if (sequence === this.searchSequence) this.results = [];
                    } finally {
                        if (sequence === this.searchSequence) this.searching = false;
                    }
                },
            }));
});
</script>
</x-layout>
