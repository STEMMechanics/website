@php
$workshopContent = isset($workshop) ? $workshop->content : '';
$workshopStatusForForm = old('status', $workshop->status ?? 'draft');
if ($workshopStatusForForm === 'private') {
    $workshopStatusForForm = 'open';
}
$savedTickets = old('tickets_json');

if ($savedTickets === null) {
$savedTickets = isset($workshop)
? json_encode(
($workshop->tickets ?? collect())->map(fn ($ticket) => [
'id' => $ticket->id,
'status' => (int) $ticket->status,
'user_id' => (string) $ticket->user_id,
'firstname' => (string) ($ticket->firstname ?? ''),
'surname' => (string) ($ticket->surname ?? ''),
'email' => (string) ($ticket->email ?? ''),
'phone' => (string) ($ticket->phone ?? ''),
])->values()->all()
)
: '[]';
}
@endphp
<x-layout>
    <x-mast backRoute="admin.workshop.index" backTitle="Workshops">{{ isset($workshop) ? 'Edit' : 'Create' }} Workshop</x-mast>

    <x-container class="mt-4">
        <form x-data="{
            type: @js(old('type', isset($workshop) && $workshop->location_id ? 'physical' : 'online')),
            status: @js($workshopStatusForForm),
            isPrivate: @js((bool) old('is_private', isset($workshop) ? $workshop->isPrivate() : false)),
            registration: @js(old('registration', $workshop->registration ?? 'none')),
            maxTickets: @js(old('max_tickets', $workshop->max_tickets ?? '')),
            locations: @js(\App\Models\Location::orderByRaw(" name='Online' DESC, name ASC")->get()->map(fn ($location) => [
            'id' => (string) $location->id,
            'name' => (string) $location->name,
            'address' => (string) ($location->address ?? ''),
            ])->values()->all()),
            selectedLocationId: @js(old('location_id', $workshop->location_id ?? '')),
            createLocationOpen: false,
            createLocationSubmitting: false,
            createLocationError: '',
            newLocation: {
            name: '',
            address: '',
            url: '',
            address_url: '',
            },
            availableUsers: @js(($users ?? collect())->map(fn ($user) => [
            'id' => (string) $user->id,
            'firstname' => (string) ($user->firstname ?? ''),
            'surname' => (string) ($user->surname ?? ''),
            'email' => (string) ($user->email ?? ''),
            'phone' => (string) ($user->phone ?? ''),
            ])->values()->all()),
            tickets: (() => {
            try {
            const parsed = JSON.parse(@js($savedTickets));
            if (!Array.isArray(parsed)) {
            return [];
            }

            return parsed.map((ticket) => ({
            id: ticket.id || null,
            status: Number.isFinite(parseInt(ticket.status, 10)) ? parseInt(ticket.status, 10) : 0,
            user_id: (ticket.user_id || '').toString(),
            firstname: ticket.firstname || '',
            surname: ticket.surname || '',
            email: ticket.email || '',
            phone: ticket.phone || '',
            }));
            } catch (e) {
            return [];
            }
            })(),
            serializeTickets() {
            const cleaned = this.tickets
            .map((ticket) => ({
            id: ticket.id || null,
            status: Number.isFinite(parseInt(ticket.status, 10)) ? parseInt(ticket.status, 10) : 0,
            user_id: (ticket.user_id || '').toString().trim(),
            firstname: (ticket.firstname || '').trim(),
            surname: (ticket.surname || '').trim(),
            email: (ticket.email || '').trim(),
            phone: (ticket.phone || '').trim(),
            }))
            .filter((ticket) => ticket.user_id !== '');

            this.$refs.ticketsJson.value = JSON.stringify(cleaned);
            },
            addTicket() {
            this.tickets.push({
            id: null,
            status: 0,
            user_id: '',
            firstname: '',
            surname: '',
            email: '',
            phone: '',
            });
            this.serializeTickets();
            },
            removeTicket(index) {
            this.tickets.splice(index, 1);
            this.serializeTickets();
            },
            applyUserDefaults(index) {
            const selectedUserId = (this.tickets[index]?.user_id || '').toString();
            const selectedUser = this.availableUsers.find((user) => user.id === selectedUserId);
            if (!selectedUser) {
            return;
            }

            this.tickets[index].firstname = selectedUser.firstname || '';
            this.tickets[index].surname = selectedUser.surname || '';
            this.tickets[index].email = selectedUser.email || '';
            this.tickets[index].phone = selectedUser.phone || '';
            this.serializeTickets();
            },
            initLocationSelection() {
            if (this.type !== 'physical') {
            this.selectedLocationId = '';
            return;
            }
            const current = (this.selectedLocationId ?? '').toString();
            if (current !== '' && this.locations.some((location) => String(location.id) === current)) {
                this.selectedLocationId = current;
                return;
            }
            if (this.locations.length > 0) {
            this.selectedLocationId = String(this.locations[0].id);
            } else {
            this.selectedLocationId = '';
            }
            },
            openCreateLocation() {
            this.createLocationError = '';
            this.newLocation = {
            name: '',
            address: '',
            url: '',
            address_url: '',
            };
            this.createLocationOpen = true;
            },
            closeCreateLocation() {
            this.createLocationOpen = false;
            this.createLocationError = '';
            },
            async submitCreateLocation() {
            if (this.createLocationSubmitting) {
            return;
            }
            this.createLocationSubmitting = true;
            this.createLocationError = '';
            try {
            const response = await fetch('{{ route('admin.location.store') }}', {
            method: 'POST',
            headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            'X-CSRF-TOKEN': '{{ csrf_token() }}',
            },
            body: JSON.stringify(this.newLocation),
            });
            const payload = await response.json();
            if (!response.ok || !payload?.success || !payload?.location) {
            const firstError = payload?.errors ? Object.values(payload.errors)?.[0]?.[0] : null;
            throw new Error(firstError || payload?.message || 'Unable to create location.');
            }
            const location = payload.location;
            this.locations = [...this.locations, location]
            .filter((value, index, array) => array.findIndex((item) => item.id === value.id) === index)
            .sort((a, b) => String(a.name || '').localeCompare(String(b.name || '')));
            this.selectedLocationId = String(location.id);
            this.closeCreateLocation();
            } catch (error) {
            this.createLocationError = error?.message || 'Unable to create location.';
            } finally {
            this.createLocationSubmitting = false;
            }
            },
            async cancelTicketById(ticketId) {
            const id = parseInt(ticketId || 0, 10);
            if (!Number.isFinite(id) || id <= 0) {
                return;
                }

                const confirmed = await new Promise((resolve) => {
                    if (window.SM && typeof window.SM.confirm === 'function') {
                        window.SM.confirm(
                            'Confirm action',
                            'Cancel this ticket now? If applicable, a tax adjustment note and refund will be issued.',
                            'Cancel Ticket',
                            (isConfirmed) => resolve(Boolean(isConfirmed))
                        );
                        return;
                    }
                    resolve(false);
                });
                if (!confirmed) {
                    return;
                }

                const urlTemplate=@js(route('admin.ticket.cancel', ['ticket'=> '__TICKET__']));
                const actionUrl = urlTemplate.replace('__TICKET__', String(id));

                const response = await fetch(actionUrl, {
                method: 'POST',
                headers: {
                'X-CSRF-TOKEN': @js(csrf_token()),
                'Accept': 'application/json',
                },
                });

                if (!response.ok) {
                if (window.SM && typeof window.SM.notice === 'function') {
                    window.SM.notice('Action failed', 'Unable to cancel ticket right now.', 'danger');
                }
                return;
                }

                window.location.reload();
                },
                }" method="POST" action="{{ route('admin.workshop.' . (isset($workshop) ? 'update' : 'store'), $workshop ?? []) }}" x-init="initLocationSelection()">
                @isset($workshop)
                @method('PUT')
                @endisset
                @csrf
                <div class="mb-4">
                    <x-ui.input label="Title" name="title" value="{!! isset($workshop) ? $workshop->title : '' !!}" />
                </div>
                <div class="mb-4">
                    <x-ui.media label="Image" name="hero_media_name" value="{{ $workshop->hero_media_name ?? '' }}" allow_uploads="true" />
                </div>
                <div class="flex flex-col sm:flex-row sm:gap-8">
                    <div class="flex-1">
                        <x-ui.select label="Type" name="type" x-model="type" x-on:change="if (type !== 'physical') { selectedLocationId = '' } else { initLocationSelection() }">
                            <option value="physical">Physical</option>
                            <option value="online">Online</option>
                        </x-ui.select>
                    </div>
                    <div class="flex-1">
                        <span x-show="type==='physical'">
                            <x-ui.select label="Location" name="location_id" x-model="selectedLocationId" x-bind:disabled="type !== 'physical'">
                                <x-slot name="labelRight">
                                    <button type="button" class="text-primary-color cursor-pointer hover:underline" x-on:click.prevent="openCreateLocation()">Create new location</button>
                                </x-slot>
                                <option value="">Select location</option>
                                <template x-for="location in locations" :key="location.id">
                                    <option :value="String(location.id)" :selected="String(selectedLocationId ?? '') === String(location.id)" x-text="location.name"></option>
                                </template>
                            </x-ui.select>
                        </span>
                    </div>
                </div>

            <div
                x-cloak
                x-show="createLocationOpen"
                x-on:keydown.escape.window="closeCreateLocation()"
                x-on:keydown.enter.prevent.stop="submitCreateLocation()"
                class="fixed inset-0 z-50 flex items-center justify-center p-4"
                role="dialog"
                aria-modal="true">
                    <div class="absolute inset-0 bg-black/40" x-on:click="closeCreateLocation()"></div>
                <div class="relative w-full max-w-xl rounded-xl bg-white p-5 shadow-xl">
                    <div class="mb-4 flex items-center justify-between">
                        <h3 class="text-lg font-semibold">Create Location</h3>
                        <button type="button" class="text-gray-500 hover:text-gray-700" x-on:click="closeCreateLocation()">
                            <i class="fa-solid fa-xmark"></i>
                        </button>
                    </div>

                    <div class="grid grid-cols-1 gap-3">
                        <x-ui.input label="Name" name="new_location_name" x-model="newLocation.name" />
                        <x-ui.input label="Address" name="new_location_address" x-model="newLocation.address" />
                        <x-ui.input label="Location URL" name="new_location_url" x-model="newLocation.url" />
                        <x-ui.input label="Address URL" name="new_location_address_url" x-model="newLocation.address_url" />
                    </div>

                    <div x-show="createLocationError" x-text="createLocationError" class="mt-2 text-sm text-red-600"></div>

                    <div class="mt-5 flex justify-end gap-3">
                        <x-ui.button type="button" color="secondary" x-on:click.prevent.stop="closeCreateLocation()">Cancel</x-ui.button>
                        <x-ui.button type="button" x-bind:disabled="createLocationSubmitting" x-on:click.prevent.stop="submitCreateLocation()">
                            <span x-show="!createLocationSubmitting">Create Location</span>
                            <span x-show="createLocationSubmitting">Creating...</span>
                        </x-ui.button>
                    </div>
                </div>
            </div>
                <div class="flex flex-col sm:flex-row sm:gap-8">
                    <div class="flex-1">
                        <x-ui.input type="datetime-local" label="Start Date" name="starts_at" value="{{ \App\Helpers::timestampNoSeconds($workshop->starts_at ?? '') }}" onchange="updatedStartsAt()" />
                    </div>
                    <div class="flex-1">
                        <x-ui.input type="datetime-local" label="End Date" name="ends_at" value="{{ \App\Helpers::timestampNoSeconds($workshop->ends_at ?? '') }}" />
                    </div>
                </div>
                <div class="flex flex-col sm:flex-row sm:gap-8">
                    <div class="flex-1">
                        <x-ui.select label="Status" name="status" x-model="status">
                            <option value="draft" {{ $workshopStatusForForm === 'draft' ? 'selected' : '' }}>Draft</option>
                            <option value="hidden" {{ $workshopStatusForForm === 'hidden' ? 'selected' : '' }}>Hidden</option>
                            <option value="scheduled" {{ $workshopStatusForForm === 'scheduled' ? 'selected' : '' }}>Opens Soon</option>
                            <option value="open" {{ $workshopStatusForForm === 'open' ? 'selected' : '' }}>Open</option>
                            <option value="full" {{ $workshopStatusForForm === 'full' ? 'selected' : '' }}>Full</option>
                            <option value="closed" {{ $workshopStatusForForm === 'closed' ? 'selected' : '' }}>Closed</option>
                            <option value="cancelled" {{ $workshopStatusForForm === 'cancelled' ? 'selected' : '' }}>Cancelled</option>
                        </x-ui.select>
                    </div>
                    <div class="flex-1">
                        <x-ui.input type="datetime-local" label="Publish Date" name="publish_at" value="{{ \App\Helpers::timestampNoSeconds($workshop->publish_at ?? '') }}" onchange="updatedPublishAt()" />
                    </div>
                </div>
                <div class="flex flex-col sm:flex-row sm:gap-8">
                    <div class="flex-1 content-center">
                        <x-ui.checkbox
                                label="Private Workshop"
                                name="is_private"
                                value="1"
                                :checked="(bool) old('is_private', isset($workshop) ? $workshop->isPrivate() : false)"
                                x-model="isPrivate"
                                no-wrapper="true"
                                 />
                    </div>
                    <div class="flex-1">
                        <x-ui.input type="datetime-local" label="Closes Date" name="closes_at" value="{{ \App\Helpers::timestampNoSeconds($workshop->closes_at ?? '') }}" />
                    </div>
                </div>
                <div class="flex flex-col sm:flex-row sm:gap-8" x-show="isPrivate">
                    <div class="flex-1">
                        <x-ui.input label="Private Code" name="private_code" value="{{ old('private_code', $workshop->private_code ?? '') }}" info="When set, users must enter this code before accessing private registration options." error="{{ $errors->first('private_code') }}" />
                    </div>
                    <div class="flex-1">
                        <x-ui.input label="Hosted For" name="hosted_for" value="{{ old('hosted_for', $workshop->hosted_for ?? '') }}" info="Shown publicly for private workshops instead of the location." error="{{ $errors->first('hosted_for') }}" />
                    </div>
                </div>
                <div class="flex flex-col sm:flex-row sm:gap-8">
                    <div class="flex-1">
                        <x-ui.input label="Price" name="price" info="Leave blank to hide from public. Also supports Free, TBD or TBC" value="{{ $workshop->price ?? '' }}" />
                    </div>
                    <div class="flex-1">
                        <x-ui.input label="Ages" name="ages" info="Leave blank to hide from public" value="{{ $workshop->ages ?? '8+' }}" />
                    </div>
                </div>
                <div class="flex flex-col sm:flex-row sm:gap-8">
                    <div class="flex-1">
                        <x-ui.select label="Registration" name="registration" x-model="registration" onchange="document.getElementsByName('registration_data').forEach((e)=>e.value='')">
                            <option value="none" {{ (old('registration', $workshop->registration ?? '')) === 'none' ? 'selected' : '' }}>None</option>
                            <option value="tickets" {{ (old('registration', $workshop->registration ?? '')) === 'tickets' ? 'selected' : '' }}>Tickets</option>
                            <option value="link" {{ (old('registration', $workshop->registration ?? '')) === 'link' ? 'selected' : '' }}>External Link</option>
                            <option value="email" {{ (old('registration', $workshop->registration ?? '')) === 'email' ? 'selected' : '' }}>External Email</option>
                            <option value="message" {{ (old('registration', $workshop->registration ?? '')) === 'message' ? 'selected' : '' }}>Custom Message</option>
                        </x-ui.select>
                    </div>
                    <div class="flex-1">
                        <span x-show="registration==='tickets'">
                            <x-ui.input type="number" min="1" step="1" label="Max Tickets" name="max_tickets" x-model="maxTickets" value="{{ old('max_tickets', $workshop->max_tickets ?? '') }}" error="{{ $errors->first('max_tickets') }}" />
                        </span>
                        <span x-show="registration==='link'">
                            <x-ui.input label="Registration URL" name="registration_url" id="registration_url" value="{!! isset($workshop) ? $workshop->registration_data : '' !!}" error="{{ $errors->first('registration_data') }}" />
                        </span>
                        <span x-show="registration==='email'">
                            <x-ui.input label="Registration Email" name="registration_email" id="registration_email" value="{{ $workshop->registration_data ?? '' }}" error="{{ $errors->first('registration_data') }}" />
                        </span>
                        <span x-show="registration==='message'">
                            <x-ui.input label="Registration Message" name="registration_message" id="registration_message" value="{{ $workshop->registration_data ?? '' }}" error="{{ $errors->first('registration_data') }}" />
                        </span>
                        <input type="hidden" name="registration_data" id="registration_data" value="{{ $workshop->registration_data ?? '' }}">
                    </div>
                </div>
                <div class="flex flex-col sm:flex-row sm:gap-8">
                    <div class="flex-1">
                        <x-ui.select label="Pick List Template" name="pick_list_template_id">
                            <x-slot name="labelRight">
                                <a href="{{ route('admin.pick-list-template.index') }}" class="text-primary-color cursor-pointer hover:underline" target="_blank">Manage templates</a>
                            </x-slot>
                            <option value="">No template</option>
                            @foreach(($pickListTemplates ?? collect()) as $pickListTemplate)
                                <option value="{{ $pickListTemplate->id }}" {{ (string) old('pick_list_template_id', $workshop->pick_list_template_id ?? '') === (string) $pickListTemplate->id ? 'selected' : '' }}>{{ $pickListTemplate->name }}</option>
                            @endforeach
                        </x-ui.select>
                        @if(isset($workshop) && $workshop->pick_list_template_id)
                            <div class="mt-1 text-xs">
                                <a class="text-primary-color hover:underline" target="_blank" href="{{ route('admin.pick-list-template.edit', $workshop->pick_list_template_id) }}">Open selected template</a>
                            </div>
                        @endif
                    </div>
                </div>
                <div class="mb-4">
                    <x-ui.editor
                        label="Content"
                        name="content"
                        value="{!! $workshopContent !!}"></x-ui.editor>
                </div>
                <div class="mb-4">
                    <x-ui.filelist
                        label="Files"
                        name="files"
                        editor="true"
                        value="{!! isset($workshop) ? $workshop->files()->orderBy('name')->get() : '' !!}"></x-ui.filelist>
                </div>
                <div class="mb-4">
                    <x-ui.filelist
                        label="Private Admin Files"
                        info="Visible to admins only from workshop admin screens. Not shown on the public workshop page."
                        name="private_files"
                        editor="true"
                        value="{!! isset($workshop) ? $workshop->files('private')->orderBy('name')->get() : '' !!}"></x-ui.filelist>
                </div>
                <div class="flex justify-end gap-4 mt-8">
                    @isset($workshop)
                    <x-ui.button type="button" color="danger" x-data x-on:click.prevent="SM.confirmDelete('{{ csrf_token() }}', 'Delete workshop?', 'Are you sure you want to delete this workshop? This action cannot be undone', '{{ route('admin.workshop.destroy', $workshop) }}')">Delete</x-ui.button>
                    @endisset
                    <x-ui.button type="submit">{{ isset($workshop) ? 'Save' : 'Create' }}</x-ui.button>
                </div>
        </form>
    </x-container>
</x-layout>

<script>
    function updatedStartsAt() {
        const startsAt = document.getElementsByName('starts_at')[0].value;
        console.log(startsAt);

        const elemEndsAt = document.getElementsByName('ends_at')[0];
        if (elemEndsAt.value === '') {
            let endsAt = new Date(startsAt);
            endsAt.setHours(endsAt.getHours() + 1);
            document.getElementsByName('ends_at')[0].value = SM.toLocalISOString(endsAt);
        }

        let closesAt = new Date(startsAt);
        closesAt.setHours(closesAt.getHours() - 2);
        document.getElementsByName('closes_at')[0].value = SM.toLocalISOString(closesAt);
    }

    function updatedPublishAt() {
        const publishAt = document.getElementsByName('publish_at')[0].value;
        const now = new Date();
        const statusElement = document.getElementsByName('status')[0];

        if (publishAt > now && statusElement && statusElement.value !== 'hidden') {
            statusElement.value = 'scheduled';
        }
    }

    document.addEventListener('DOMContentLoaded', function() {
        const elementIds = ['registration_url', 'registration_email', 'registration_message'];
        const registrationElem = document.getElementById('registration_data');

        if (registrationElem) {
            elementIds.forEach(id => {
                const elem = document.getElementById(id);
                if (elem) {
                    elem.addEventListener('change', function(event) {
                        registrationElem.value = event.target.value;
                    });
                }
            })
        }
    });

    /* Initalize */
    const elemPublishAt = document.getElementsByName('publish_at')[0];
    if (elemPublishAt && elemPublishAt.value === '') {
        let publishAt = new Date();
        document.getElementsByName('publish_at')[0].value = SM.toLocalISOString(publishAt);
    }
</script>
