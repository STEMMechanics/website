@php
    $formatWebhookResponseBody = static function (?string $body): ?string {
        $raw = is_string($body) ? trim($body) : '';
        if ($raw === '') {
            return null;
        }

        $decoded = json_decode($raw, true);
        if (json_last_error() !== JSON_ERROR_NONE || ! is_array($decoded)) {
            return $body;
        }

        $pretty = json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

        return is_string($pretty) ? $pretty : $body;
    };
@endphp

@if($webhookLogs->isEmpty())
    <section class="rounded-3xl border border-dashed border-gray-300 bg-white p-6">
        <h2 class="text-lg font-semibold text-gray-900">No webhook activity found</h2>
        <p class="mt-2 text-sm leading-6 text-gray-600">No STEMCraft webhook logs match the current filters.</p>
    </section>
@else
    <div class="space-y-4 lg:hidden" data-webhook-list="mobile">
        @foreach($webhookLogs as $log)
            @php
                $isOutbound = $log->direction === \App\Models\MinecraftWebhookLog::DIRECTION_OUTBOUND;
                $canRetry = $isOutbound && $log->status === \App\Models\MinecraftWebhookLog::STATUS_FAILED;
                $nextRetryAt = $log->nextRetryAt();
                $errorSummary = $log->errorSummary();
                $troubleshootingHint = $log->troubleshootingHint();
                $statusClass = match ($log->status) {
                    'delivered', 'received' => 'text-green-700',
                    'failed', 'rejected' => 'text-red-700',
                    'duplicate', 'ignored' => 'text-amber-700',
                    default => 'text-gray-700',
                };
                $payloadPretty = is_array($log->payload)
                    ? json_encode($log->payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
                    : null;
                $formattedResponseBody = $formatWebhookResponseBody($log->response_body);
            @endphp
            <section
                class="rounded-3xl border border-gray-200 bg-white p-5 shadow-sm {{ (string) request('highlight') === (string) $log->id ? 'ring-2 ring-amber-300' : '' }}"
                data-webhook-row-list="mobile"
                data-webhook-row-key="{{ $log->id }}"
            >
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
                @if($troubleshootingHint)
                    <div class="mt-3 rounded-2xl bg-amber-50 px-4 py-3 text-sm text-amber-900">{{ $troubleshootingHint }}</div>
                @endif
                <details class="mt-4 rounded-2xl bg-gray-50 px-4 py-3" data-refresh-key="webhook-{{ $log->id }}-mobile-content">
                    <summary class="cursor-pointer text-sm font-semibold text-gray-900">View content</summary>
                    <div class="mt-3 space-y-3 text-sm text-gray-700">
                        @if($log->payload)
                            <div>
                                <div class="flex items-center justify-between gap-2">
                                    <div class="text-xs font-semibold uppercase tracking-wide text-gray-500">Payload</div>
                                    <button
                                        type="button"
                                        class="inline-flex items-center rounded-md border border-gray-200 px-2 py-1 text-xs font-semibold text-gray-600 transition hover:border-gray-300 hover:text-gray-900"
                                        data-copy-json-target="webhook-payload-{{ $log->id }}-mobile"
                                        title="Copy payload JSON"
                                        aria-label="Copy payload JSON"
                                    >
                                        <i class="fa-regular fa-copy" aria-hidden="true"></i>
                                    </button>
                                </div>
                                <pre id="webhook-payload-{{ $log->id }}-mobile" class="mt-1 overflow-auto rounded-xl bg-white p-3 text-xs text-gray-800 whitespace-pre-wrap">{{ $payloadPretty }}</pre>
                            </div>
                        @endif
                        @if($formattedResponseBody)
                            <div>
                                <div class="flex items-center justify-between gap-2">
                                    <div class="text-xs font-semibold uppercase tracking-wide text-gray-500">Response</div>
                                    <button
                                        type="button"
                                        class="inline-flex items-center rounded-md border border-gray-200 px-2 py-1 text-xs font-semibold text-gray-600 transition hover:border-gray-300 hover:text-gray-900"
                                        data-copy-json-target="webhook-response-{{ $log->id }}-mobile"
                                        title="Copy response JSON"
                                        aria-label="Copy response JSON"
                                    >
                                        <i class="fa-regular fa-copy" aria-hidden="true"></i>
                                    </button>
                                </div>
                                <pre id="webhook-response-{{ $log->id }}-mobile" class="mt-1 overflow-auto rounded-xl bg-white p-3 text-xs text-gray-800 whitespace-pre-wrap">{{ $formattedResponseBody }}</pre>
                            </div>
                        @endif
                    </div>
                </details>
                @if($canRetry)
                    <div class="mt-4 flex justify-end">
                        <form method="POST" action="{{ route('admin.stemcraft.webhook-logs.retry', $log) }}">
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
            </x-slot:header>
            <x-slot:body>
                @foreach($webhookLogs as $log)
                    @php
                        $isOutbound = $log->direction === \App\Models\MinecraftWebhookLog::DIRECTION_OUTBOUND;
                        $canRetry = $isOutbound && $log->status === \App\Models\MinecraftWebhookLog::STATUS_FAILED;
                        $nextRetryAt = $log->nextRetryAt();
                        $errorSummary = $log->errorSummary();
                        $troubleshootingHint = $log->troubleshootingHint();
                        $statusClass = match ($log->status) {
                            'delivered', 'received' => 'text-green-700',
                            'failed', 'rejected' => 'text-red-700',
                            'duplicate', 'ignored' => 'text-amber-700',
                            default => 'text-gray-700',
                        };
                        $payloadPretty = is_array($log->payload)
                            ? json_encode($log->payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
                            : null;
                        $formattedResponseBody = $formatWebhookResponseBody($log->response_body);
                    @endphp
                    <tr
                        class="{{ (string) request('highlight') === (string) $log->id ? 'bg-amber-50/80' : '' }}"
                        data-webhook-row-list="desktop"
                        data-webhook-row-key="{{ $log->id }}"
                    >
                        <td class="whitespace-nowrap!">#{{ $log->id }}</td>
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
                            @if($troubleshootingHint)
                                <div class="mt-2 text-xs text-amber-800">{{ $troubleshootingHint }}</div>
                            @endif
                            @if($nextRetryAt)
                                <div class="mt-2 text-xs text-amber-700">Next retry {{ $nextRetryAt->format('j M Y g:i a') }}</div>
                            @endif
                            @if($log->payload || $formattedResponseBody)
                                <details class="mt-3" data-refresh-key="webhook-{{ $log->id }}-desktop-content">
                                    <summary class="cursor-pointer text-xs font-semibold text-gray-700">View content</summary>
                                    @if($log->payload)
                                        <div class="mt-2 flex items-center justify-between gap-2">
                                            <div class="text-xs font-semibold uppercase tracking-wide text-gray-500">Payload</div>
                                            <button
                                                type="button"
                                                class="inline-flex items-center rounded-md border border-gray-200 px-2 py-1 text-xs font-semibold text-gray-600 transition hover:border-gray-300 hover:text-gray-900"
                                                data-copy-json-target="webhook-payload-{{ $log->id }}-desktop"
                                                title="Copy payload JSON"
                                                aria-label="Copy payload JSON"
                                            >
                                                <i class="fa-regular fa-copy" aria-hidden="true"></i>
                                            </button>
                                        </div>
                                        <pre id="webhook-payload-{{ $log->id }}-desktop" class="mt-1 max-h-56 overflow-auto rounded-xl bg-gray-50 p-3 text-xs text-gray-800 whitespace-pre-wrap">{{ $payloadPretty }}</pre>
                                    @endif
                                    @if($formattedResponseBody)
                                        <div class="mt-2 flex items-center justify-between gap-2">
                                            <div class="text-xs font-semibold uppercase tracking-wide text-gray-500">Response</div>
                                            <button
                                                type="button"
                                                class="inline-flex items-center rounded-md border border-gray-200 px-2 py-1 text-xs font-semibold text-gray-600 transition hover:border-gray-300 hover:text-gray-900"
                                                data-copy-json-target="webhook-response-{{ $log->id }}-desktop"
                                                title="Copy response JSON"
                                                aria-label="Copy response JSON"
                                            >
                                                <i class="fa-regular fa-copy" aria-hidden="true"></i>
                                            </button>
                                        </div>
                                        <pre id="webhook-response-{{ $log->id }}-desktop" class="mt-1 max-h-40 overflow-auto rounded-xl bg-gray-50 p-3 text-xs text-gray-800 whitespace-pre-wrap">{{ $formattedResponseBody }}</pre>
                                    @endif
                                </details>
                            @endif
                            @if($canRetry)
                                <div class="mt-3">
                                    <form method="POST" action="{{ route('admin.stemcraft.webhook-logs.retry', $log) }}">
                                        @csrf
                                        <x-ui.button type="submit" color="outline">Retry</x-ui.button>
                                    </form>
                                </div>
                            @elseif($isOutbound && $nextRetryAt)
                                <div class="mt-3 text-xs text-amber-700">This webhook is already queued to retry automatically.</div>
                            @endif
                        </td>
                        <td class="whitespace-nowrap!">
                            <div class="font-semibold {{ $statusClass }}">{{ $log->status }}</div>
                            @if($log->response_status)
                                <div class="text-xs text-gray-500">HTTP {{ $log->response_status }}</div>
                            @endif
                        </td>
                        <td class="hidden xl:table-cell text-center">{{ $log->attempt_count }}</td>
                        <td class="hidden xl:table-cell">{{ $log->processed_at?->format('j M Y g:i a') ?? '-' }}</td>
                    </tr>
                @endforeach
            </x-slot:body>
        </x-ui.table>
    </div>

    <div data-webhook-pagination>
        {{ $webhookLogs->appends(request()->query())->links() }}
    </div>
@endif
