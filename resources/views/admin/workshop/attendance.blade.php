@php
    $isTicketedWorkshop = $workshop->registration === 'tickets';
    $seedEntries = old('entries');

    if (! is_array($seedEntries)) {
        $seedEntries = $dropIns->map(function ($entry) {
            $childName = trim((string) ($entry->child_name ?? ''));
            if ($childName === '') {
                $childName = trim((string) (($entry->firstname ?? '').' '.($entry->surname ?? '')));
            }

            return [
                'id' => (int) $entry->id,
                'child_name' => $childName,
                'guardian_name' => (string) ($entry->guardian_name ?? ''),
                'email' => (string) ($entry->email ?? ''),
                'phone' => (string) ($entry->phone ?? ''),
                'media_consent' => (bool) ($entry->media_consent ?? false),
            ];
        })->values()->all();
    }
@endphp

<x-layout>
    <x-mast backRoute="admin.workshop.index" backTitle="Workshops">Workshop Attendance</x-mast>

    <x-container>
        <div class="mb-4 rounded-lg border border-gray-200 bg-gray-50 p-4">
            <div class="text-lg font-semibold">{{ $workshop->title }}</div>
            <div class="text-sm text-gray-600">Starts: {{ $workshop->starts_at?->format('M j, Y g:i a') ?? '-' }}</div>
            <div class="text-sm text-gray-600">Location: {{ $workshop->getLocationName() }}</div>
            <div class="mt-2 flex gap-2 flex-wrap">
                <x-ui.button type="link" color="outline" href="{{ route('admin.workshop.edit', $workshop) }}">Edit Workshop</x-ui.button>
                <x-ui.button type="link" color="outline" href="{{ route('admin.workshop.pick-list', $workshop) }}">Pick List</x-ui.button>
                <x-ui.button type="link" color="outline" href="{{ route('admin.workshop.attendance.pdf', $workshop) }}">Export PDF</x-ui.button>
                <x-ui.button type="link" color="outline" href="{{ route('admin.workshop.attendance.csv', $workshop) }}">Export CSV</x-ui.button>
                @if($isTicketedWorkshop)
                    <x-ui.button type="link" color="outline" href="{{ route('admin.workshop.tickets', $workshop) }}">View Tickets</x-ui.button>
                @else
                    <x-ui.button type="link" href="{{ route('admin.workshop.attendance', ['workshop' => $workshop, 'kiosk' => 1]) }}">Kiosk Sign-In Mode</x-ui.button>
                @endif
            </div>
        </div>

        <div class="mb-6">
            <x-ui.filelist
                label="Private Admin Files"
                value="{!! $workshop->files('private')->orderBy('name')->get() !!}" />
        </div>

        @if($isTicketedWorkshop)
            <div class="rounded-lg border border-gray-200 p-4 mb-6">
                <h2 class="text-lg font-semibold mb-3">Ticketed Attendance</h2>
                @if($activeTickets->isEmpty())
                    <p class="text-sm text-gray-600">No active tickets available to mark attendance.</p>
                @else
                    <form method="POST" action="{{ route('admin.workshop.attendance.tickets', $workshop) }}">
                        @csrf
                        <x-ui.table>
                            <x-slot:header>
                                <th>Attended</th>
                                <th>Ticket Ref</th>
                                <th>Attendee</th>
                                <th>Contact</th>
                                <th>Status</th>
                            </x-slot:header>
                            <x-slot:body>
                                @foreach($activeTickets as $ticket)
                                    <tr>
                                        <td>
                                            <input type="checkbox" name="attended_ticket_ids[]" value="{{ $ticket->id }}" {{ $ticket->attended_at ? 'checked' : '' }}>
                                        </td>
                                        <td>{{ $ticket->reference_code ?: $ticket->id }}</td>
                                        <td>{{ trim((string) (($ticket->firstname ?? '').' '.($ticket->surname ?? ''))) ?: '-' }}</td>
                                        <td>
                                            <div>{{ $ticket->email ?: '-' }}</div>
                                            <div class="text-xs text-gray-500">{{ $ticket->phone ?: '-' }}</div>
                                        </td>
                                        <td>{{ $ticket->customer_status_label }}</td>
                                    </tr>
                                @endforeach
                            </x-slot:body>
                        </x-ui.table>
                        <div class="mt-4 flex justify-end">
                            <x-ui.button type="submit">Save Ticket Attendance</x-ui.button>
                        </div>
                    </form>
                @endif
            </div>
        @endif

        <div class="">
            <form method="POST" action="{{ route('admin.workshop.attendance.dropin.sync', $workshop) }}" x-data="{
                entries: @js($seedEntries),
                submitting: false,
                newBlankEntry() {
                    return {
                        id: 0,
                        child_name: '',
                        guardian_name: '',
                        email: '',
                        phone: '',
                        media_consent: false,
                    };
                },
                isBlankEntry(entry) {
                    return String(entry?.child_name || '').trim() === ''
                        && String(entry?.guardian_name || '').trim() === ''
                        && String(entry?.email || '').trim() === ''
                        && String(entry?.phone || '').trim() === ''
                        && !Boolean(entry?.media_consent);
                },
                hasSingleTrailingBlank() {
                    if (this.entries.length === 0) {
                        return false;
                    }
                    const blankCount = this.entries.filter((entry) => this.isBlankEntry(entry)).length;
                    return blankCount === 1 && this.isBlankEntry(this.entries[this.entries.length - 1]);
                },
                ensureSingleTrailingBlank() {
                    const nonBlank = this.entries.filter((entry) => !this.isBlankEntry(entry));
                    this.entries = [...nonBlank, this.newBlankEntry()];
                },
                handleRowChange(index) {
                    const isLast = index === (this.entries.length - 1);
                    if (isLast && !this.isBlankEntry(this.entries[index])) {
                        this.entries.push(this.newBlankEntry());
                        return;
                    }
                    if (!this.hasSingleTrailingBlank()) {
                        this.ensureSingleTrailingBlank();
                    }
                },
                removeEntry(index) {
                    this.entries.splice(index, 1);
                    this.ensureSingleTrailingBlank();
                },
                addEntry() {
                    if (!this.hasSingleTrailingBlank()) {
                        this.ensureSingleTrailingBlank();
                    }
                },
            }" x-init="ensureSingleTrailingBlank()" x-on:submit="submitting = true">
                @csrf
                <div class="flex items-center justify-between mb-3">
                    <h2 class="text-lg font-semibold">{{ $isTicketedWorkshop ? 'Drop-In Attendance' : 'Attendance Records' }}</h2>
                </div>

                <div class="overflow-x-auto">
                    <table class="min-w-full border border-gray-200 rounded-md">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="text-left p-2 border-b">Child Name</th>
                                <th class="text-left p-2 border-b">Parent/Guardian</th>
                                <th class="text-left p-2 border-b">Email</th>
                                <th class="text-left p-2 border-b">Phone</th>
                                <th class="text-left p-2 border-b">Media</th>
                                <th class="text-left p-2 border-b">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <template x-for="(entry, index) in entries" :key="index">
                                <tr class="border-b last:border-b-0">
                                    <td class="p-2 align-top">
                                        <input type="hidden" x-bind:name="`entries[${index}][id]`" x-model="entry.id">
                                        <x-ui.input
                                            name="child_name_placeholder"
                                            :noLabel="true"
                                            class="mb-0"
                                            fieldClasses="mt-0"
                                            x-model="entry.child_name"
                                            x-bind:name="`entries[${index}][child_name]`"
                                            x-on:input="entry.child_name = $event.target.value; handleRowChange(index)"
                                            x-on:change="entry.child_name = $event.target.value; handleRowChange(index)" />
                                    </td>
                                    <td class="p-2 align-top">
                                        <x-ui.input
                                            name="guardian_name_placeholder"
                                            :noLabel="true"
                                            class="mb-0"
                                            fieldClasses="mt-0"
                                            x-model="entry.guardian_name"
                                            x-bind:name="`entries[${index}][guardian_name]`"
                                            x-on:input="entry.guardian_name = $event.target.value; handleRowChange(index)"
                                            x-on:change="entry.guardian_name = $event.target.value; handleRowChange(index)" />
                                    </td>
                                    <td class="p-2 align-top">
                                        <x-ui.input
                                            type="email"
                                            name="email_placeholder"
                                            :noLabel="true"
                                            class="mb-0"
                                            fieldClasses="mt-0"
                                            x-model="entry.email"
                                            x-bind:name="`entries[${index}][email]`"
                                            x-on:input="entry.email = $event.target.value; handleRowChange(index)"
                                            x-on:change="entry.email = $event.target.value; handleRowChange(index)" />
                                    </td>
                                    <td class="p-2 align-top">
                                        <x-ui.input
                                            name="phone_placeholder"
                                            :noLabel="true"
                                            class="mb-0"
                                            fieldClasses="mt-0"
                                            x-model="entry.phone"
                                            x-bind:name="`entries[${index}][phone]`"
                                            x-on:input="entry.phone = $event.target.value; handleRowChange(index)"
                                            x-on:change="entry.phone = $event.target.value; handleRowChange(index)" />
                                    </td>
                                    <td class="p-2 align-middle">
                                        <input type="hidden" x-bind:name="`entries[${index}][media_consent]`" value="0">
                                        <input type="checkbox"
                                               class="h-5 w-5 rounded border-gray-300 text-primary-color focus:ring-primary-color"
                                               x-bind:name="`entries[${index}][media_consent]`"
                                               value="1"
                                               x-model="entry.media_consent"
                                               x-on:change="entry.media_consent = $event.target.checked; handleRowChange(index)">
                                    </td>
                                    <td class="p-2 align-top">
                                        <button type="button" class="text-red-600 hover:text-red-700" x-on:click="removeEntry(index)" title="Delete row">
                                            <i class="fa-solid fa-trash"></i>
                                        </button>
                                    </td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>

                <div class="mt-4 flex justify-end">
                    <x-ui.button type="submit" x-bind:disabled="submitting">
                        <span x-show="!submitting">Save Attendance Records</span>
                        <span x-show="submitting" class="inline-flex items-center gap-2">
                            <i class="fa-solid fa-circle-notch animate-spin"></i>
                            <span>Saving...</span>
                        </span>
                    </x-ui.button>
                </div>
            </form>
        </div>
    </x-container>
</x-layout>
