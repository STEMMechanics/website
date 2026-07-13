@php
    $status = app(\App\Services\StemcraftServerStatusService::class)->publicStatus();

    $allowedStatuses = ['online', 'offline', 'maintenance'];
    $statusKey = (string) ($status['status'] ?? 'offline');

    if (! in_array($statusKey, $allowedStatuses, true)) {
        $statusKey = 'offline';
    }

    $statusConfig = [
        'online' => [
            'label' => 'Online',
            'icon' => 'fa-circle-check',
            'badge' => 'bg-emerald-100 text-emerald-800 ring-emerald-200',
            'icon_colour' => 'text-emerald-600',
            'summary' => 'The server is online and ready to join.',
        ],
        'offline' => [
            'label' => 'Offline',
            'icon' => 'fa-circle-xmark',
            'icon_colour' => 'text-red-600',
            'summary' => 'The server is currently offline.',
        ],
        'maintenance' => [
            'label' => 'Maintenance',
            'icon' => 'fa-screwdriver-wrench',
            'badge' => 'bg-amber-100 text-amber-800 ring-amber-200',
            'icon_colour' => 'text-amber-600',
            'summary' => 'The server is temporarily unavailable for maintenance.',
        ],
    ];

    $currentStatus = $statusConfig[$statusKey];

    $checkedAt = null;

    if (! empty($status['checked_at'])) {
        try {
            $checkedAt = \Illuminate\Support\Carbon::parse($status['checked_at']);
        } catch (\Throwable) {
            $checkedAt = null;
        }
    }

    $playersOnline = isset($status['players_online'])
        ? (int) $status['players_online']
        : null;

    $maxPlayers = isset($status['max_players'])
        ? (int) $status['max_players']
        : null;

    $version = trim((string) ($status['version'] ?? ''));
    $serverAddress = trim((string) ($status['server_address'] ?? ''));
    $message = trim((string) ($status['message'] ?? ''));
    $stale = (bool) ($status['stale'] ?? false);
@endphp

<div {{ $attributes->class('overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm') }}>
    <div class="p-5 sm:p-6">
        <div class="flex flex-col gap-5 sm:flex-row sm:items-start sm:justify-between">
            <div class="flex flex-col min-w-0 items-start pr-8">
                <div class="flex gap-3 items-center">
                    <div class="mt-0.5 flex w-10 shrink-0 items-center justify-center rounded-full">
                        <i class="fa-solid text-2xl {{ $currentStatus['icon'] }} {{ $currentStatus['icon_colour'] }}"></i>
                    </div>
                    <h2 class="text-lg font-semibold text-gray-900">
                        STEMCraft Server
                    </h2>
                </div>
                <p class="ml-13 text-sm {{ $currentStatus['icon_colour'] }}">
                    {{ $currentStatus['summary'] }}
                </p>
            </div>
        </div>

        <dl class="mt-6 flex flex-col gap-3">
            <div class="rounded-md bg-slate-50 p-4">
                <dt class="text-xs font-semibold uppercase tracking-wide text-gray-500">
                    Players
                </dt>

                <dd class="mt-2 text-base font-semibold text-gray-900">
                    @if($playersOnline !== null && $maxPlayers !== null)
                        {{ $playersOnline }} of {{ $maxPlayers }}
                    @elseif($playersOnline !== null)
                        {{ $playersOnline }} online
                    @else
                        Not available
                    @endif
                </dd>

                @if($playersOnline !== null && $maxPlayers !== null)
                    <p class="mt-1 text-xs text-gray-500">currently online</p>
                @endif
            </div>

            <div class="rounded-md bg-slate-50 p-4">
                <dt class="text-xs font-semibold uppercase tracking-wide text-gray-500">
                    Version
                </dt>

                <dd class="mt-2 text-base font-semibold text-gray-900">
                    {{ $version !== '' ? $version : 'Not available' }}
                </dd>

                <p class="mt-1 text-xs text-gray-500">supported version</p>
            </div>

            <div class="rounded-md bg-slate-50 p-4">
                <dt class="text-xs font-semibold uppercase tracking-wide text-gray-500">
                    {{ $stale ? 'Last updated' : 'Last checked' }}
                </dt>

                <dd class="mt-2 text-base font-semibold text-gray-900">
                    {{ $checkedAt ? $checkedAt->diffForHumans() : 'Not available' }}
                </dd>

                @if($stale)
                    <p class="mt-1 text-xs font-medium text-amber-700">
                        Cached status
                    </p>
                @endif
            </div>
        </dl>

        @if($message !== '')
            <div class="mt-5 rounded-md bg-amber-50 px-4 py-3 text-sm leading-6 text-amber-900">
                {{ $message }}
            </div>
        @elseif($stale)
            <div class="mt-5 rounded-md bg-amber-50 px-4 py-3 text-sm leading-6 text-amber-900">
                Live status could not be refreshed. Showing the most recent server information
                @if($checkedAt)
                    from {{ $checkedAt->diffForHumans() }}.
                @endif
            </div>
        @endif
    </div>

    @if($serverAddress !== '')
        <div class="border-t border-gray-200 bg-slate-50 p-5 sm:p-6">
            <div class="text-xs font-semibold uppercase tracking-wide text-gray-500">
                Server address
            </div>

            <div class="mt-3 flex flex-col gap-3 sm:flex-row sm:items-center">
                <code class="min-w-0 flex-1 break-all text-lg font-semibold text-gray-900">
                    {{ $serverAddress }}
                </code>
            </div>
        </div>
    @endif
</div>

@once
    <script>
        document.addEventListener('click', async (event) => {
            const button = event.target.closest('.stemcraft-copy-address');

            if (!button) {
                return;
            }

            const value = String(button.dataset.copyValue || '');
            const label = button.querySelector('span');

            if (value === '' || !label) {
                return;
            }

            const previousLabel = label.textContent;

            try {
                await navigator.clipboard.writeText(value);

                label.textContent = 'Copied';
                button.classList.add('text-emerald-700', 'border-emerald-300');

                window.setTimeout(() => {
                    label.textContent = previousLabel;
                    button.classList.remove('text-emerald-700', 'border-emerald-300');
                }, 1600);
            } catch (error) {
                label.textContent = 'Copy failed';

                window.setTimeout(() => {
                    label.textContent = previousLabel;
                }, 1600);
            }
        });
    </script>
@endonce
