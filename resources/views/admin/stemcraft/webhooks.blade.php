<x-layout>
    <x-mast title="STEMCraft" :tabs="[
        ['title' => 'Accounts', 'route' => route('admin.stemcraft.index')],
        ['title' => 'Punishments', 'route' => route('admin.stemcraft.punishments.index')],
        ['title' => 'Webhooks', 'route' => route('admin.stemcraft.webhooks.index')],
        ['title' => 'RCON', 'route' => route('admin.stemcraft.rcon.index')],
    ]" />

    <x-container class="mt-8" inner-class="flex flex-col gap-8">
        <section class="rounded-3xl border border-gray-200 bg-white p-5 shadow-sm sm:p-6">
            <div class="max-w-3xl">
                <h2 class="text-xl font-semibold text-gray-900">Webhook activity</h2>
                <p class="mt-2 text-sm leading-6 text-gray-600">Track what the website has sent to the Minecraft server, what the website has received back from the server, whether delivery succeeded, and retry failed outbound calls when needed.</p>
            </div>

            <form method="GET" action="{{ route('admin.stemcraft.webhooks.index') }}" class="mt-6 grid gap-4 lg:grid-cols-4 items-center">
                <x-ui.input name="search" label="Search" value="{{ $search }}" />
                <x-ui.select name="direction" label="Direction">
                    <option value="">All directions</option>
                    <option value="outbound" {{ $selectedDirection === 'outbound' ? 'selected' : '' }}>Outbound</option>
                    <option value="inbound" {{ $selectedDirection === 'inbound' ? 'selected' : '' }}>Inbound</option>
                </x-ui.select>
                <x-ui.select name="status" label="Status">
                    <option value="">All statuses</option>
                    @foreach(['queued', 'pending', 'delivered', 'failed', 'received', 'ignored', 'rejected', 'duplicate'] as $status)
                        <option value="{{ $status }}" {{ $selectedStatus === $status ? 'selected' : '' }}>{{ ucfirst($status) }}</option>
                    @endforeach
                </x-ui.select>
                <x-ui.button type="submit" class="mt-1">Filter</x-ui.button>
            </form>
        </section>

        @if($webhookLogs->isEmpty())
            <section class="rounded-3xl border border-dashed border-gray-300 bg-white p-6">
                <h2 class="text-lg font-semibold text-gray-900">No webhook activity found</h2>
                <p class="mt-2 text-sm leading-6 text-gray-600">No STEMCraft webhook logs match the current filters.</p>
            </section>
        @else
            <div class="space-y-4 lg:hidden">
                @foreach($webhookLogs as $log)
                    @php
                        $isOutbound = $log->direction === \App\Models\MinecraftWebhookLog::DIRECTION_OUTBOUND;
                        $canRetry = $isOutbound && $log->status === \App\Models\MinecraftWebhookLog::STATUS_FAILED;
                        $nextRetryAt = $log->nextRetryAt();
                        $errorSummary = $log->errorSummary();
                        $statusClass = match ($log->status) {
                            'delivered', 'received' => 'text-green-700',
                            'failed', 'rejected' => 'text-red-700',
                            'duplicate', 'ignored' => 'text-amber-700',
                            default => 'text-gray-700',
                        };
                    @endphp
                    <section class="rounded-3xl border border-gray-200 bg-white p-5 shadow-sm {{ (string) request('highlight') === (string) $log->id ? 'ring-2 ring-amber-300' : '' }}">
                        <div class="flex flex-wrap items-center gap-3">
                            <span class="inline-flex rounded-full bg-gray-100 px-3 py-1 text-xs font-semibold uppercase tracking-wide text-gray-700">{{ $log->direction }}</span>
                            <span class="text-sm font-semibold {{ $statusClass }}">{{ $log->status }}</span>
                            <span class="text-xs text-gray-500">#{{ $log->id }}</span>
                        </div>
                        <div class="mt-3 text-sm font-semibold text-gray-900">{{ $log->event ?: 'Unknown event' }}</div>
                        <div class="mt-1 text-xs font-mono text-gray-500 break-all">{{ $log->delivery_id ?: 'No delivery id' }}</div>
                        <div class="mt-4 grid gap-3 sm:grid-cols-2">
                            <div>
                                <div class="text-xs font-semibold uppercase tracking-wide text-gray-500">Target</div>
                                <div class="mt-1 text-sm text-gray-900 break-all">{{ $log->target_url ?: '-' }}</div>
                            </div>
                            <div>
                                <div class="text-xs font-semibold uppercase tracking-wide text-gray-500">Attempts</div>
                                <div class="mt-1 text-sm text-gray-900">{{ $log->attempt_count }}</div>
                            </div>
                        </div>
                        <div class="mt-3 text-xs text-gray-500">
                            Last attempted {{ $log->last_attempted_at?->format('j M Y g:i a') ?? '-' }}
                            @if($nextRetryAt)
                                | Next retry {{ $nextRetryAt->format('j M Y g:i a') }}
                            @endif
                            @if($log->processed_at)
                                | Processed {{ $log->processed_at->format('j M Y g:i a') }}
                            @endif
                        </div>
                        @if($errorSummary)
                            <div class="mt-3 rounded-2xl bg-red-50 px-4 py-3 text-sm text-red-800" title="{{ $log->error_message }}">{{ $errorSummary }}</div>
                        @endif
                        <details class="mt-4 rounded-2xl bg-gray-50 px-4 py-3">
                            <summary class="cursor-pointer text-sm font-semibold text-gray-900">View content</summary>
                            <div class="mt-3 space-y-3 text-sm text-gray-700">
                                @if($log->payload)
                                    <div>
                                        <div class="text-xs font-semibold uppercase tracking-wide text-gray-500">Payload</div>
                                        <pre class="mt-1 overflow-auto rounded-xl bg-white p-3 text-xs text-gray-800 whitespace-pre-wrap">{{ json_encode($log->payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>
                                    </div>
                                @endif
                                @if($log->response_body)
                                    <div>
                                        <div class="text-xs font-semibold uppercase tracking-wide text-gray-500">Response</div>
                                        <pre class="mt-1 overflow-auto rounded-xl bg-white p-3 text-xs text-gray-800 whitespace-pre-wrap">{{ $log->response_body }}</pre>
                                    </div>
                                @endif
                            </div>
                        </details>
                        @if($canRetry)
                            <div class="mt-4 flex justify-end">
                                <form method="POST" action="{{ route('admin.stemcraft.webhooks.retry', $log) }}">
                                    @csrf
                                    <x-ui.button type="submit" color="outline">Retry</x-ui.button>
                                </form>
                            </div>
                        @elseif($isOutbound && $nextRetryAt)
                            <div class="mt-4 text-right text-xs text-amber-700">
                                This webhook is already queued to retry automatically.
                            </div>
                        @endif
                    </section>
                @endforeach
            </div>

            <div class="hidden lg:block">
                <x-ui.table>
                    <x-slot:header>
                        <th>ID</th>
                        <th>Details</th>
                        <th>Status</th>
                        <th class="hidden xl:table-cell">Attempts</th>
                        <th class="hidden xl:table-cell">Processed</th>
                        <th>Actions</th>
                    </x-slot:header>
                    <x-slot:body>
                        @foreach($webhookLogs as $log)
                            @php
                                $isOutbound = $log->direction === \App\Models\MinecraftWebhookLog::DIRECTION_OUTBOUND;
                                $canRetry = $isOutbound && $log->status === \App\Models\MinecraftWebhookLog::STATUS_FAILED;
                                $nextRetryAt = $log->nextRetryAt();
                                $errorSummary = $log->errorSummary();
                                $statusClass = match ($log->status) {
                                    'delivered', 'received' => 'text-green-700',
                                    'failed', 'rejected' => 'text-red-700',
                                    'duplicate', 'ignored' => 'text-amber-700',
                                    default => 'text-gray-700',
                                };
                            @endphp
                            <tr class="{{ (string) request('highlight') === (string) $log->id ? 'bg-amber-50/80' : '' }}">
                                <td class="whitespace-nowrap">#{{ $log->id }}</td>
                                <td>
                                    <div class="flex flex-wrap items-center gap-3">
                                        <span class="inline-flex rounded-full bg-gray-100 px-2.5 py-1 text-xs font-semibold uppercase tracking-wide text-gray-700">{{ $log->direction }}</span>
                                        <span class="font-semibold text-gray-900">{{ $log->event ?: 'Unknown event' }}</span>
                                    </div>
                                    <div class="mt-1 text-xs font-mono text-gray-500 break-all">{{ $log->delivery_id ?: 'No delivery id' }}</div>
                                    @if($log->target_url)
                                        <div class="mt-1 text-xs text-gray-600 break-all">{{ $log->target_url }}</div>
                                    @endif
                                    @if($errorSummary)
                                        <div class="mt-2 text-xs text-red-700" title="{{ $log->error_message }}">{{ $errorSummary }}</div>
                                    @endif
                                    @if($nextRetryAt)
                                        <div class="mt-2 text-xs text-amber-700">Next retry {{ $nextRetryAt->format('j M Y g:i a') }}</div>
                                    @endif
                                    @if($log->payload)
                                        <details class="mt-3">
                                            <summary class="cursor-pointer text-xs font-semibold text-gray-700">View content</summary>
                                            <pre class="mt-2 max-h-56 overflow-auto rounded-xl bg-gray-50 p-3 text-xs text-gray-800 whitespace-pre-wrap">{{ json_encode($log->payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>
                                            @if($log->response_body)
                                                <div class="mt-2 text-xs font-semibold uppercase tracking-wide text-gray-500">Response</div>
                                                <pre class="mt-1 max-h-40 overflow-auto rounded-xl bg-gray-50 p-3 text-xs text-gray-800 whitespace-pre-wrap">{{ $log->response_body }}</pre>
                                            @endif
                                        </details>
                                    @endif
                                </td>
                                <td>
                                    <div class="font-semibold {{ $statusClass }}">{{ $log->status }}</div>
                                    @if($log->response_status)
                                        <div class="text-xs text-gray-500">HTTP {{ $log->response_status }}</div>
                                    @endif
                                </td>
                                <td class="hidden xl:table-cell">{{ $log->attempt_count }}</td>
                                <td class="hidden xl:table-cell">{{ $log->processed_at?->format('j M Y g:i a') ?? '-' }}</td>
                                <td>
                                    <div class="flex justify-center gap-3 whitespace-nowrap">
                                        @if($canRetry)
                                            <form method="POST" action="{{ route('admin.stemcraft.webhooks.retry', $log) }}">
                                                @csrf
                                                <button type="submit" class="hover:text-primary-color" title="Retry webhook">
                                                    <i class="fa-solid fa-rotate-right"></i>
                                                </button>
                                            </form>
                                        @elseif($isOutbound && $nextRetryAt)
                                            <span class="text-amber-500" title="Retry already scheduled"><i class="fa-solid fa-clock-rotate-left"></i></span>
                                        @else
                                            <span class="text-gray-300" title="Inbound webhook"><i class="fa-solid fa-arrow-down"></i></span>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </x-slot:body>
                </x-ui.table>
            </div>

            {{ $webhookLogs->appends(request()->query())->links() }}
        @endif
    </x-container>
</x-layout>
