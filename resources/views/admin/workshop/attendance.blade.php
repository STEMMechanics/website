<x-layout>
    <x-mast backRoute="admin.workshop.index" backTitle="Workshops">Workshop Attendance</x-mast>

    <x-container>
        <div class="mb-4 rounded-lg border border-gray-200 bg-gray-50 p-4">
            <div class="text-lg font-semibold">{{ $workshop->title }}</div>
            <div class="text-sm text-gray-600">Starts: {{ $workshop->starts_at?->format('M j, Y g:i a') ?? '-' }}</div>
            <div class="text-sm text-gray-600">Location: {{ $workshop->getLocationName() }}</div>
            <div class="mt-2 flex gap-2">
                <x-ui.button type="link" color="outline" href="{{ route('admin.workshop.edit', $workshop) }}">Edit Workshop</x-ui.button>
                @if($workshop->registration === 'tickets')
                <x-ui.button type="link" color="outline" href="{{ route('admin.workshop.tickets', $workshop) }}">View Tickets</x-ui.button>
                @endif
            </div>
        </div>

        <div class="mb-6">
            <x-ui.filelist
                label="Private Admin Files"
                value="{!! $workshop->files('private')->orderBy('name')->get() !!}" />
        </div>

        @if($workshop->registration === 'tickets')
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

        <div class="rounded-lg border border-gray-200 p-4">
            <h2 class="text-lg font-semibold mb-3">Drop-In Attendance</h2>
            <form method="POST" action="{{ route('admin.workshop.attendance.dropin.store', $workshop) }}" class="grid grid-cols-1 md:grid-cols-4 gap-3 mb-4">
                @csrf
                <x-ui.input name="firstname" label="First Name" required />
                <x-ui.input name="surname" label="Surname" />
                <x-ui.input type="email" name="email" label="Email" />
                <x-ui.input name="phone" label="Phone" />
                <div class="md:col-span-4 flex justify-end">
                    <x-ui.button type="submit">Add Drop-In</x-ui.button>
                </div>
            </form>

            @if($dropIns->isEmpty())
            <p class="text-sm text-gray-600">No drop-in attendees recorded yet.</p>
            @else
            <x-ui.table>
                <x-slot:header>
                    <th>Name</th>
                    <th>Contact</th>
                    <th>Recorded</th>
                    <th>Actions</th>
                </x-slot:header>
                <x-slot:body>
                    @foreach($dropIns as $entry)
                    <tr>
                        <td>{{ trim((string) (($entry->firstname ?? '').' '.($entry->surname ?? ''))) ?: '-' }}</td>
                        <td>
                            <div>{{ $entry->email ?: '-' }}</div>
                            <div class="text-xs text-gray-500">{{ $entry->phone ?: '-' }}</div>
                        </td>
                        <td>{{ $entry->attended_at?->format('M j, Y g:i a') ?? '-' }}</td>
                        <td>
                            <form method="POST" action="{{ route('admin.workshop.attendance.dropin.destroy', ['workshop' => $workshop, 'attendance' => $entry]) }}" x-data="{ confirmedSubmit: false }" x-on:submit.prevent="
                                if (confirmedSubmit) {
                                    confirmedSubmit = false;
                                    $el.submit();
                                    return;
                                }
                                if (window.SM && typeof window.SM.confirm === 'function') {
                                    window.SM.confirm('Confirm action', 'Remove this attendance entry?', 'Remove', (isConfirmed) => {
                                        if (!isConfirmed) return;
                                        confirmedSubmit = true;
                                        $el.requestSubmit();
                                    });
                                }
                            ">
                                @csrf
                                <button type="submit" class="hover:text-red-600" title="Remove"><i class="fa-solid fa-trash"></i></button>
                            </form>
                        </td>
                    </tr>
                    @endforeach
                </x-slot:body>
            </x-ui.table>
            @endif
        </div>
    </x-container>
</x-layout>
