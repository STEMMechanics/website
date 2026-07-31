@php
    $editing = isset($organisation);
    $initialContacts = $editing
        ? $organisation->contacts->map(fn ($contact) => [
            'id' => (string) $contact->id,
            'name' => $contact->getName(),
            'email' => (string) $contact->email,
            'company' => (string) ($contact->company ?? ''),
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
                                    <td class="hidden px-3 py-2 text-gray-600 md:table-cell" x-text="contact.company || '-'"></td>
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
                                <span class="block text-xs text-gray-500" x-text="`${contact.email}${contact.company ? ` · ${contact.company}` : ''}`"></span>
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
