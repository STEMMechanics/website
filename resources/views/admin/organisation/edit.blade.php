@php
    $editing = isset($organisation);
    $initialContacts = $editing
        ? $organisation->contacts->map(fn ($contact) => [
            'id' => (string) $contact->id,
            'name' => $contact->getName(),
            'email' => (string) $contact->email,
            'organisation_name' => (string) ($contact->primaryOrganisation?->name ?? ''),
            'edit_url' => route('admin.user.edit', $contact),
        ])->values()->all()
        : [];
@endphp

<x-layout>
    <x-mast backRoute="admin.organisation.index" backTitle="Organisations">{{ $editing ? 'Edit' : 'Create' }} Organisation</x-mast>

    <x-container>
        @if($editing)
            <x-ui.toolbar>
                <x-slot:right>
                    <x-ui.button color="outline" href="{{ route('admin.workshop.history', ['organisation_id' => $organisation->id, 'include_children' => 1]) }}">
                        <i class="fa-solid fa-clock-rotate-left mr-2"></i>Workshop History
                    </x-ui.button>
                </x-slot:right>
            </x-ui.toolbar>
        @endif

        <form
            method="POST"
            action="{{ $editing ? route('admin.organisation.update', $organisation) : route('admin.organisation.store') }}"
            x-data="{
                contacts: @js($initialContacts),
                results: [],
                search: '',
                searching: false,
                searchSequence: 0,
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
            }"
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

            <x-ui.select label="Parent organisation" name="parent_id" info="Use this for a service or branch belonging to a larger organisation.">
                <option value="">No parent organisation</option>
                @foreach($organisations as $candidate)
                    <option value="{{ $candidate->id }}" @selected((string) old('parent_id', $organisation->parent_id ?? '') === (string) $candidate->id)>{{ $candidate->name }}</option>
                @endforeach
            </x-ui.select>

            <div class="mb-4 rounded-xl border border-gray-200 bg-white p-4">
                <div class="mb-3">
                    <h3 class="font-semibold text-gray-900">Billing Address</h3>
                    <p class="text-xs text-gray-500">Used on invoices and quotes when a contact inherits the organisation billing address.</p>
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
                    <p class="text-xs text-gray-500">Used for deliveries when a contact inherits the organisation shipping address.</p>
                </div>
                <x-ui.input label="Address" name="shipping_address" value="{{ old('shipping_address', $organisation->shipping_address ?? '') }}" />
                <x-ui.input label="Address 2" name="shipping_address2" value="{{ old('shipping_address2', $organisation->shipping_address2 ?? '') }}" />
                <x-ui.input label="City" name="shipping_city" value="{{ old('shipping_city', $organisation->shipping_city ?? '') }}" />
                <div class="grid gap-4 sm:grid-cols-2">
                    <x-ui.input label="State" name="shipping_state" value="{{ old('shipping_state', $organisation->shipping_state ?? '') }}" />
                    <x-ui.input label="Postcode" name="shipping_postcode" value="{{ old('shipping_postcode', $organisation->shipping_postcode ?? '') }}" />
                </div>
                <x-ui.input label="Country" name="shipping_country" value="{{ old('shipping_country', $organisation->shipping_country ?? '') }}" />
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

            <x-ui.input label="Notes" name="notes" value="{{ old('notes', $organisation->notes ?? '') }}" />

            <div class="mb-4 rounded-xl border border-gray-200 bg-white p-4">
                <div class="mb-3">
                    <h3 class="font-semibold text-gray-900">Contacts</h3>
                    <p class="text-xs text-gray-500">Customer records that work for or represent this organisation.</p>
                </div>

                <div class="mb-4 overflow-x-auto rounded-lg border border-gray-200" x-show="contacts.length > 0">
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead class="bg-gray-50 text-left text-xs font-semibold uppercase tracking-wide text-gray-600">
                            <tr>
                                <th class="px-3 py-2">Contact</th>
                                <th class="px-3 py-2">Email</th>
                                <th class="hidden px-3 py-2 md:table-cell">Organisation</th>
                                <th class="w-12 px-3 py-2"><span class="sr-only">Action</span></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <template x-for="(contact, index) in contacts" :key="contact.id">
                                <tr>
                                    <td class="px-3 py-2">
                                        <input type="hidden" x-bind:name="`contact_ids[${index}]`" :value="contact.id">
                                        <a :href="contact.edit_url" class="text-primary-color hover:underline" x-text="contact.name"></a>
                                    </td>
                                    <td class="px-3 py-2 text-gray-600" x-text="contact.email"></td>
                                    <td class="hidden px-3 py-2 text-gray-600 md:table-cell" x-text="contact.organisation_name || '-'"></td>
                                    <td class="px-3 py-2 text-center">
                                        <button type="button" class="text-gray-500 hover:text-red-600" x-on:click.prevent="remove(contact.id)" title="Remove contact" aria-label="Remove contact">
                                            <i class="fa-solid fa-link-slash"></i>
                                        </button>
                                    </td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>

                <div class="mb-4 rounded-lg border border-dashed border-gray-200 bg-gray-50 px-3 py-3 text-sm text-gray-500" x-show="contacts.length === 0" x-cloak>
                    No contacts are currently linked.
                </div>

                <x-ui.input
                    id="organisation_contact_search"
                    type="search"
                    name=""
                    label="Find contacts"
                    x-model="search"
                    x-on:input.debounce.350ms="findContacts()"
                    placeholder="Search name, email, or organisation"
                    class="mb-3"
                />

                <div class="rounded-lg border border-dashed border-gray-200 bg-gray-50 px-3 py-3 text-sm text-gray-500" x-show="search.trim().length < 2" x-cloak>
                    Enter at least 2 characters to search contacts.
                </div>
                <div class="rounded-lg border border-dashed border-gray-200 bg-gray-50 px-3 py-3 text-sm text-gray-500" x-show="searching" x-cloak>
                    Searching contacts…
                </div>
                <div class="max-h-72 overflow-y-auto rounded-lg border border-gray-200 bg-gray-50" x-show="search.trim().length >= 2 && !searching" x-cloak>
                    <template x-for="contact in results" :key="contact.id">
                        <button type="button" class="flex w-full items-start gap-3 border-b border-gray-200 bg-white px-3 py-2 text-left text-sm last:border-b-0 hover:bg-sky-50" x-on:click.prevent="add(contact)">
                            <i class="fa-solid fa-plus mt-1 text-primary-color"></i>
                            <span class="min-w-0">
                                <span class="block text-gray-900" x-text="contact.name"></span>
                                <span class="block text-xs text-gray-500" x-text="`${contact.email}${contact.organisation_name ? ` · ${contact.organisation_name}` : ''}`"></span>
                            </span>
                        </button>
                    </template>
                    <div class="px-3 py-3 text-sm text-gray-500" x-show="results.length === 0" x-cloak>No unlinked contacts found.</div>
                </div>
                <p class="mt-1 text-xs text-gray-500">Results are limited to 25 matches.</p>
            </div>

            @if($editing && $organisation->children->isNotEmpty())
                <div class="mb-4 flex flex-wrap items-center gap-2">
                    <span class="text-sm font-semibold text-gray-700">Child organisations:</span>
                    @foreach($organisation->children as $child)
                        <x-ui.button type="button" color="outline" href="{{ route('admin.organisation.edit', $child) }}">{{ $child->name }}</x-ui.button>
                    @endforeach
                </div>
            @endif

            <div class="mt-8 flex justify-end gap-4">
                @if($editing)
                    <x-ui.button type="button" color="danger" x-data x-on:click.prevent="SM.confirmDelete('{{ csrf_token() }}', 'Delete organisation?', 'Workshops and contacts will be retained but unlinked from this organisation.', '{{ route('admin.organisation.destroy', $organisation) }}')">Delete</x-ui.button>
                @endif
                <x-ui.button type="submit">Save</x-ui.button>
            </div>
        </form>
    </x-container>
</x-layout>
