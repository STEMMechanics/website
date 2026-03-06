<section class="rounded-3xl border border-gray-200 bg-white p-5 shadow-sm sm:p-6">
    <div class="max-w-3xl">
        <h2 class="text-xl font-semibold text-gray-900">Server Management</h2>
        <p class="mt-2 text-sm leading-6 text-gray-600">Live status and console commands are sent through the existing signed STEMCraft webhook bridge.</p>
    </div>

    <div class="mt-5 grid gap-4 md:grid-cols-2 xl:grid-cols-3">
        <div class="rounded-2xl border border-gray-200 bg-gray-50 p-4">
            <div class="text-xs font-semibold uppercase tracking-wide text-gray-500">Webhook target</div>
            <div class="mt-1 break-all font-mono text-sm text-gray-900">{{ $connection['target'] }}</div>
        </div>
        <div class="rounded-2xl border border-gray-200 bg-gray-50 p-4">
            <div class="text-xs font-semibold uppercase tracking-wide text-gray-500">Bridge status</div>
            <div class="mt-1 text-sm font-semibold {{ $connection['configured'] ? 'text-green-700' : 'text-amber-700' }}">
                {{ $connection['configured'] ? 'Configured' : 'Needs setup' }}
            </div>
        </div>
        <div class="rounded-2xl border border-gray-200 bg-gray-50 p-4">
            <div class="text-xs font-semibold uppercase tracking-wide text-gray-500">Settings</div>
            <div class="mt-1 text-sm text-gray-900">
                <a class="text-primary-color hover:underline" href="{{ route('admin.site_option.index', ['search' => 'minecraft.server-webhook']) }}">Webhook URL</a>
                <span class="text-gray-400">•</span>
                <a class="text-primary-color hover:underline" href="{{ route('admin.site_option.index', ['search' => 'minecraft.webhook-secret']) }}">Webhook secret</a>
            </div>
        </div>
    </div>

    @if(! $connection['configured'])
        <div class="mt-5 rounded-2xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900">
            Configure `minecraft.server-webhook-url` and `minecraft.webhook-secret`, then enable `webhook_bridge.allow_status_requests` and `webhook_bridge.allow_remote_commands` in the plugin if you want status and command support.
        </div>
    @elseif($statusError)
        <div class="mt-5 rounded-2xl border border-red-200 bg-red-50 p-4 text-sm text-red-800">{{ $statusError }}</div>
    @endif
</section>

<section class="rounded-3xl border border-gray-200 bg-white p-5 shadow-sm sm:p-6">
    <div class="flex flex-col gap-2 md:flex-row md:items-end md:justify-between">
        <div>
            <h2 class="text-xl font-semibold text-gray-900">Status</h2>
            <p class="mt-2 text-sm leading-6 text-gray-600">Current server information returned by `server.status.request`.</p>
        </div>
        <div class="flex justify-start md:justify-end">
            <x-ui.button type="button" color="primary-outline" data-management-refresh>Refresh status</x-ui.button>
        </div>
    </div>

    @if(! $connection['configured'])
        <p class="mt-4 text-sm text-gray-500">No server status is available until the webhook bridge is configured.</p>
    @elseif($statusError)
        <p class="mt-4 text-sm text-gray-500">Status could not be loaded from the Minecraft plugin.</p>
    @elseif($statusCards === [])
        <p class="mt-4 text-sm text-gray-500">The Minecraft plugin did not return a status payload.</p>
    @else
        <div class="mt-4 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            @foreach($statusCards as $card)
                <div class="rounded-2xl border border-gray-200 bg-gray-50 p-4">
                    <div class="text-xs font-semibold uppercase tracking-wide text-gray-500">{{ $card['label'] }}</div>

                    @if(! empty($card['segments']) && is_array($card['segments']))
                        <div class="mt-2 flex flex-wrap gap-3">
                            @foreach($card['segments'] as $segment)
                                <div class="min-w-0">
                                    <div class="text-[11px] font-semibold uppercase tracking-wide text-gray-500">{{ $segment['label'] }}</div>
                                    <div class="text-sm font-semibold {{ $segment['class'] }}">{{ $segment['value'] }}</div>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <div class="mt-1 break-words text-sm font-semibold text-gray-900">{{ $card['value'] }}</div>
                    @endif
                </div>
            @endforeach
        </div>
    @endif
</section>

<section class="grid gap-8 xl:grid-cols-[minmax(0,1.2fr)_22rem]">
    <section class="rounded-3xl border border-gray-200 bg-white p-5 shadow-sm sm:p-6">
        <h2 class="text-xl font-semibold text-gray-900">Worlds</h2>

        @if($worldRows === [])
            <p class="mt-4 text-sm text-gray-500">No world information is available.</p>
        @else
            <div class="mt-4 overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead>
                        <tr class="border-b border-gray-200 text-left text-xs uppercase tracking-wide text-gray-500">
                            <th class="px-3 py-2">World</th>
                            <th class="px-3 py-2">Players</th>
                            <th class="px-3 py-2">Loaded Chunks</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($worldRows as $world)
                            <tr class="border-b border-gray-100 last:border-b-0">
                                <td class="px-3 py-2 font-medium text-gray-900">{{ $world['name'] }}</td>
                                <td class="px-3 py-2 text-gray-700">{{ $world['players'] }}</td>
                                <td class="px-3 py-2 text-gray-700">{{ $world['loaded_chunks'] }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </section>

    <section class="rounded-3xl border border-gray-200 bg-white p-5 shadow-sm sm:p-6">
        <h2 class="text-xl font-semibold text-gray-900">Details</h2>

        @if($serverDetails === [])
            <p class="mt-4 text-sm text-gray-500">No extra server details are available.</p>
        @else
            <div class="mt-4 space-y-4">
                @foreach($serverDetails as $detail)
                    <div>
                        <div class="text-xs font-semibold uppercase tracking-wide text-gray-500">{{ $detail['label'] }}</div>
                        <div class="mt-1 break-words text-sm text-gray-900">{{ $detail['value'] }}</div>
                    </div>
                @endforeach
            </div>
        @endif
    </section>
</section>
