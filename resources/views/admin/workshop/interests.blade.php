<x-layout>
    <x-mast backRoute="admin.workshop.index" backTitle="Workshops">Workshop Interests</x-mast>

    <x-container class="mt-4">
        <div class="mb-6 flex flex-wrap items-start justify-between gap-4">
            <div>
                <h1 class="text-2xl font-bold text-gray-900">{{ $workshop->title }}</h1>
                <p class="mt-1 text-sm text-gray-600">People recorded as interested in this workshop. The workshop creator is included automatically.</p>
            </div>
            <div class="flex flex-wrap gap-3">
                <x-ui.button color="primary-outline" href="{{ route('workshop.show', $workshop) }}">View Workshop</x-ui.button>
                <x-ui.button color="primary-outline" href="{{ route('admin.workshop.edit', $workshop) }}">Edit Workshop</x-ui.button>
                @if($workshop->registration === 'tickets')
                    <x-ui.button color="primary-outline" href="{{ route('admin.workshop.tickets', $workshop) }}">View Tickets</x-ui.button>
                @endif
            </div>
        </div>

        <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <h2 class="text-lg font-bold text-gray-900">Interest Registrations</h2>
                    <p class="mt-1 text-sm text-gray-600">Includes direct registrations and linked account details.</p>
                </div>
                <x-ui.badge color="warning">
                    {{ number_format((int) ($workshop->interests_count ?? $interestRegistrations->count())) }}
                    {{ \Illuminate\Support\Str::plural('registration', (int) ($workshop->interests_count ?? $interestRegistrations->count())) }}
                </x-ui.badge>
            </div>

            @if($interestRegistrations->isEmpty())
                <p class="mt-4 text-sm text-gray-600">No one has registered interest for this workshop yet.</p>
            @else
                <div class="mt-4">
                    <x-ui.table variant="listing" :mobile-cards="true" table-class="w-full text-sm">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-2 text-left">Name</th>
                                <th class="px-4 py-2 text-left">Contact</th>
                                <th class="px-4 py-2 text-left">Account</th>
                                <th class="px-4 py-2 text-left">Registered</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($interestRegistrations as $interest)
                                @php
                                    $linkedUser = $interest->user;
                                    $resolvedName = trim((string) ($interest->name ?? '')) ?: trim((string) ($linkedUser?->getName() ?? '')) ?: '-';
                                    $resolvedEmail = trim((string) ($interest->email ?? ''));
                                    if ($resolvedEmail === '') {
                                        $resolvedEmail = trim((string) ($linkedUser?->email ?? ''));
                                    }
                                    $resolvedPhone = trim((string) ($interest->phone ?? ''));
                                    if ($resolvedPhone === '') {
                                        $resolvedPhone = trim((string) ($linkedUser?->phone ?? ''));
                                    }
                                    $accountLabel = match (true) {
                                        (string) ($linkedUser?->getKey() ?? '') === (string) $workshop->user_id => 'Workshop creator',
                                        $linkedUser !== null => 'Linked account',
                                        default => 'No linked account',
                                    };
                                @endphp
                                <tr class="border-t border-gray-100 align-top">
                                    <td data-label="Name" data-mobile-primary class="px-4 py-3">
                                        <div class="font-semibold text-gray-900">{{ $resolvedName }}</div>
                                    </td>
                                    <td data-label="Contact" data-mobile-wide class="px-4 py-3">
                                        <div>{{ $resolvedEmail !== '' ? $resolvedEmail : '-' }}</div>
                                        <div class="mt-1 text-xs text-gray-500">{{ $resolvedPhone !== '' ? $resolvedPhone : '-' }}</div>
                                    </td>
                                    <td data-label="Account" data-mobile-wide class="px-4 py-3">
                                        <x-ui.badge color="gray" size="xs">{{ $accountLabel }}</x-ui.badge>
                                        @if($linkedUser !== null)
                                            <div class="mt-2 text-xs text-gray-500">{{ $linkedUser->getName() }}</div>
                                        @endif
                                    </td>
                                    <td data-label="Registered" data-mobile-wide class="px-4 py-3 text-gray-600">
                                        <x-ui.date-time>{{ $interest->created_at?->format('j M Y g:i a') ?? '-' }}</x-ui.date-time>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </x-ui.table>
                </div>
            @endif
        </div>
    </x-container>
</x-layout>
