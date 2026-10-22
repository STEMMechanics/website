@php
    $serverAddress = trim((string) \App\Models\SiteOption::value(
        'stemcraft.server-status.server-address',
        \App\Models\SiteOption::defaultValue('stemcraft.server-status.server-address') ?? 'play.stemcraft.com.au',
    ));
@endphp

<div
    {{ $attributes->class('overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm') }}
    data-stemcraft-server-status-card
    data-status-url="{{ route('api.stemcraft.status') }}"
    aria-busy="true"
    aria-live="polite"
>
    <div class="p-5 sm:p-6">
        <div class="flex flex-col gap-5 sm:flex-row sm:items-start sm:justify-between">
            <div class="flex flex-col min-w-0 items-start pr-8">
                <div class="flex gap-3 items-center">
                    <div class="mt-0.5 flex w-10 shrink-0 items-center justify-center rounded-full" data-status-icon>
                        <i class="fa-solid fa-spinner fa-spin text-2xl text-gray-500" aria-hidden="true"></i>
                    </div>
                    <h2 class="text-lg font-semibold text-gray-900">
                        STEMCraft Server
                    </h2>
                </div>
                <p class="ml-13 text-sm text-gray-600" data-status-summary>
                    Checking server status…
                </p>
            </div>
        </div>

        <dl class="mt-6 flex flex-col gap-3">
            <div class="rounded-md bg-slate-50 p-4">
                <dt class="text-xs font-semibold uppercase tracking-wide text-gray-500">
                    Players
                </dt>

                <dd class="mt-2 text-base font-semibold text-gray-900" data-status-players>
                    Checking…
                </dd>

                <p class="mt-1 text-xs text-gray-500" data-status-players-note hidden></p>
            </div>

            <div class="rounded-md bg-slate-50 p-4">
                <dt class="text-xs font-semibold uppercase tracking-wide text-gray-500">
                    Version
                </dt>

                <dd class="mt-2 text-base font-semibold text-gray-900" data-status-version>
                    Checking…
                </dd>

                <p class="mt-1 text-xs text-gray-500">supported version</p>
            </div>

        </dl>

        <div class="mt-5 rounded-md bg-amber-50 px-4 py-3 text-sm leading-6 text-amber-900" data-status-message hidden></div>
    </div>

    @if($serverAddress !== '')
        <div class="border-t border-gray-200 bg-slate-50 p-5 sm:p-6">
            <div class="text-xs font-semibold uppercase tracking-wide text-gray-500">
                Server address
            </div>

            <div class="mt-3 flex flex-col gap-3 sm:flex-row sm:items-center">
                <code class="min-w-0 flex-1 break-all text-lg font-semibold text-gray-900" data-status-server-address>
                    {{ $serverAddress }}
                </code>
            </div>
        </div>
    @endif

    <noscript>
        <div class="border-t border-gray-200 bg-amber-50 px-5 py-4 text-sm text-amber-900 sm:px-6">
            Enable JavaScript to see the live server status.
        </div>
    </noscript>
</div>

@once
    <script nonce="{{ \Illuminate\Support\Facades\Vite::cspNonce() }}">
        (() => {
            const statusConfigurations = {
                online: {
                    icon: 'fa-circle-check',
                    iconColour: 'text-emerald-600',
                    summary: 'The server is online and ready to join.',
                },
                offline: {
                    icon: 'fa-circle-xmark',
                    iconColour: 'text-red-600',
                    summary: 'The server is currently offline.',
                },
                maintenance: {
                    icon: 'fa-screwdriver-wrench',
                    iconColour: 'text-amber-600',
                    summary: 'The server is temporarily unavailable for maintenance.',
                },
                error: {
                    icon: 'fa-circle-exclamation',
                    iconColour: 'text-gray-600',
                    summary: 'The server status could not be checked right now.',
                },
            };

            const iconClasses = [
                'fa-spinner',
                'fa-spin',
                'fa-circle-check',
                'fa-circle-xmark',
                'fa-screwdriver-wrench',
                'fa-circle-exclamation',
                'text-gray-500',
                'text-emerald-600',
                'text-red-600',
                'text-amber-600',
            ];

            const summaryColours = [
                'text-gray-600',
                'text-emerald-600',
                'text-red-600',
                'text-amber-600',
            ];

            const nullableInteger = (value) => {
                if (value === null || value === undefined || value === '') {
                    return null;
                }

                const number = Number(value);

                return Number.isFinite(number) ? Math.max(0, Math.trunc(number)) : null;
            };

            const textValue = (value) => String(value ?? '').trim();

            const setText = (element, value) => {
                if (element) {
                    element.textContent = value;
                }
            };

            const renderStatus = (card, status) => {
                const statusKey = Object.hasOwn(statusConfigurations, status?.status)
                    ? status.status
                    : 'offline';
                const configuration = statusConfigurations[statusKey];
                const icon = card.querySelector('[data-status-icon] i');
                const summary = card.querySelector('[data-status-summary]');
                const players = nullableInteger(status?.players_online);
                const maxPlayers = nullableInteger(status?.max_players);
                const version = textValue(status?.version);
                const message = textValue(status?.message);
                const stale = Boolean(status?.stale);

                if (icon) {
                    icon.classList.remove(...iconClasses);
                    icon.classList.add('fa-solid', 'text-2xl', configuration.icon, configuration.iconColour);
                }

                if (summary) {
                    summary.classList.remove(...summaryColours);
                    summary.classList.add(configuration.iconColour);
                    summary.textContent = configuration.summary;
                }

                if (players !== null && maxPlayers !== null) {
                    setText(card.querySelector('[data-status-players]'), `${players} of ${maxPlayers}`);
                    setText(card.querySelector('[data-status-players-note]'), 'currently online');
                    card.querySelector('[data-status-players-note]')?.removeAttribute('hidden');
                } else if (players !== null) {
                    setText(card.querySelector('[data-status-players]'), `${players} online`);
                    card.querySelector('[data-status-players-note]')?.setAttribute('hidden', '');
                } else {
                    setText(card.querySelector('[data-status-players]'), 'Not available');
                    card.querySelector('[data-status-players-note]')?.setAttribute('hidden', '');
                }

                setText(card.querySelector('[data-status-version]'), version || 'Not available');

                const messageElement = card.querySelector('[data-status-message]');
                if (messageElement) {
                    if (message !== '') {
                        messageElement.textContent = message;
                        messageElement.removeAttribute('hidden');
                    } else if (stale) {
                        messageElement.textContent = 'Live status could not be refreshed. Showing the most recent server information.';
                        messageElement.removeAttribute('hidden');
                    } else {
                        messageElement.textContent = '';
                        messageElement.setAttribute('hidden', '');
                    }
                }

                const serverAddress = textValue(status?.server_address);
                if (serverAddress !== '') {
                    setText(card.querySelector('[data-status-server-address]'), serverAddress);
                }

                card.setAttribute('aria-busy', 'false');
            };

            const loadStatus = async (card) => {
                try {
                    const response = await fetch(card.dataset.statusUrl, {
                        headers: { Accept: 'application/json' },
                        cache: 'no-store',
                    });

                    if (!response.ok) {
                        throw new Error(`Status request failed with HTTP ${response.status}`);
                    }

                    renderStatus(card, await response.json());
                } catch {
                    renderStatus(card, {
                        status: 'error',
                        message: 'The server status could not be checked right now.',
                    });
                }
            };

            const initialise = () => {
                document.querySelectorAll('[data-stemcraft-server-status-card]').forEach((card) => {
                    loadStatus(card);
                });
            };

            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', initialise, { once: true });
            } else {
                initialise();
            }
        })();
    </script>
@endonce
