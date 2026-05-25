@php
    $balanceData = is_array($balance ?? null) ? $balance : [];
    $balanceFlat = \Illuminate\Support\Arr::dot($balanceData);
    $balanceValue = null;

    foreach (['available_balance', 'balance', 'credit_balance', 'credits', 'remaining', 'amount'] as $needle) {
        foreach ($balanceFlat as $key => $value) {
            if (str_ends_with($key, $needle) && (is_numeric($value) || is_string($value))) {
                $balanceValue = is_numeric($value) ? number_format((int) floor((float) $value), 0) : trim((string) $value);
                break 2;
            }
        }
    }
@endphp

<x-layout>
    <x-mast>Sent SMS</x-mast>

    <x-container>
        <div
            data-sent-sms-root
            x-data="{
                sendSmsOpen: @js(old('recipient') !== null || old('message') !== null || old('reference') !== null || $errors->any()),
                smsRecipients: @js(old('recipient', '')),
                smsMessage: @js(old('message', '')),
                recipientError: '',
                csrfToken: document.querySelector('meta[name=&quot;csrf-token&quot;]')?.content || '',
                smsStats: {
                    characters: 0,
                    recipients: 0,
                    segments: 0,
                    totalMessages: 0,
                    label: '0 characters • 0 numbers • 0 estimated messages',
                },
                gsmBasicCharacters: new Set(Array.from(`@£$¥èéùìòÇ
Øø
ÅåΔ_ΦΓΛΩΠΨΣΘΞ !&quot;#¤%&amp;'()*+,-./0123456789:;&lt;=&gt;?¡ABCDEFGHIJKLMNOPQRSTUVWXYZÄÖÑÜ§¿abcdefghijklmnopqrstuvwxyzäöñüà`)),
                gsmExtendedCharacters: new Set(Array.from(`^{}\\[~]|€`)),
                countRecipients(value) {
                    return String(value || '')
                        .split(';')
                        .map((item) => item.trim())
                        .filter((item) => item !== '')
                        .length;
                },
                validateRecipient(value) {
                    const trimmed = String(value || '').trim();
                    if (trimmed === '') {
                        return false;
                    }

                    const normalized = trimmed.replace(/[^\d+]/g, '');

                    if (normalized.startsWith('+')) {
                        return /^\+\d{8,15}$/.test(normalized);
                    }

                    if (/^04\d{8}$/.test(normalized)) {
                        return true;
                    }

                    if (/^61\d{9}$/.test(normalized)) {
                        return true;
                    }

                    if (/^4\d{8}$/.test(normalized)) {
                        return true;
                    }

                    return false;
                },
                validateRecipients(field) {
                    const raw = String(this.smsRecipients || '').trim();
                    const invalid = raw === ''
                        ? []
                        : raw.split(';')
                            .map((item) => item.trim())
                            .filter((item) => item !== '')
                            .filter((item) => !this.validateRecipient(item));

                    this.recipientError = invalid.length === 0
                        ? ''
                        : `Invalid number${invalid.length === 1 ? '' : 's'}: ${invalid.join(', ')}`;

                    if (field instanceof HTMLInputElement || field instanceof HTMLTextAreaElement) {
                        field.setCustomValidity(this.recipientError);
                        field.reportValidity();
                    }
                },
                countGsmUnits(message) {
                    let units = 0;
                    for (const character of Array.from(message || '')) {
                        if (this.gsmBasicCharacters.has(character)) {
                            units += 1;
                            continue;
                        }

                        if (this.gsmExtendedCharacters.has(character)) {
                            units += 2;
                            continue;
                        }

                        return null;
                    }

                    return units;
                },
                messageStats() {
                    const message = String(this.smsMessage || '');
                    const characters = Array.from(message).length;
                    const recipients = this.countRecipients(this.smsRecipients);

                    if (characters === 0 || recipients === 0) {
                        return {
                            characters,
                            recipients,
                            segments: 0,
                            totalMessages: 0,
                            label: `${characters} characters • ${recipients} ${recipients === 1 ? 'number' : 'numbers'} • 0 estimated messages`,
                        };
                    }

                    const gsmUnits = this.countGsmUnits(message);
                    const segments = gsmUnits === null
                        ? (characters <= 70 ? 1 : Math.ceil(characters / 67))
                        : (gsmUnits <= 160 ? 1 : Math.ceil(gsmUnits / 153));
                    const totalMessages = recipients * segments;

                    return {
                        characters,
                        recipients,
                        segments,
                        totalMessages,
                        label: `${characters} characters • ${recipients} ${recipients === 1 ? 'number' : 'numbers'} • ${totalMessages} estimated ${totalMessages === 1 ? 'message' : 'messages'}`,
                    };
                },
                async acknowledgeReply(reply) {
                    if (!reply || reply.busy || reply.acknowledged) {
                        return;
                    }

                    reply.busy = true;

                    try {
                        const response = await fetch(reply.ackUrl, {
                            method: 'POST',
                            headers: {
                                'Accept': 'application/json',
                                'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
                                'X-CSRF-TOKEN': this.csrfToken,
                                'X-Requested-With': 'XMLHttpRequest',
                            },
                            body: new URLSearchParams({
                                _method: 'PATCH',
                            }).toString(),
                        });

                        const payload = await response.json().catch(() => ({}));

                        if (!response.ok || payload.ok !== true) {
                            throw new Error(payload.message || 'Unable to acknowledge reply.');
                        }

                        reply.acknowledged = true;
                    } catch (error) {
                        const message = error instanceof Error ? error.message : 'Unable to acknowledge reply.';
                        if (window.SM && typeof window.SM.notice === 'function') {
                            window.SM.notice('Acknowledge failed', message, 'danger');
                        } else {
                            console.error(message);
                        }
                    } finally {
                        reply.busy = false;
                    }
                },
            }"
            x-init="smsStats = messageStats(); $watch('smsRecipients', () => smsStats = messageStats()); $watch('smsMessage', () => smsStats = messageStats())"
            @keydown.escape.window="if (sendSmsOpen) sendSmsOpen = false"
        >
            <div class="my-4 flex items-center justify-between gap-4">
                <div class="min-w-0">
                    <div class="text-xs font-semibold uppercase tracking-wide text-gray-500">Remaining messages</div>
                    <div class="mt-1 text-2xl font-semibold text-gray-900">
                        {{ $balanceValue !== null ? $balanceValue : 'Not available' }}
                    </div>
                </div>
                <x-ui.button
                    type="button"
                    class="gap-3"
                    x-on:click.prevent="sendSmsOpen = true"
                >
                    <i class="fa-solid fa-comment-sms"></i>
                    Send SMS
                </x-ui.button>
            </div>

            <form method="GET" action="{{ route('admin.server.sent-sms') }}" class="mb-4 flex w-full flex-row flex-wrap items-end gap-3 rounded-lg border border-gray-200 bg-white p-4 shadow-sm">
                <div class="w-full min-w-[12rem] sm:w-auto sm:flex-none">
                    <x-ui.select label="Status" name="status" innerClass="w-full" selectClass="pr-10" class="mb-0">
                        <option value="">All statuses</option>
                        @foreach($statuses as $status)
                            <option value="{{ $status }}" @selected(request()->query('status') === $status)>{{ ucfirst($status) }}</option>
                        @endforeach
                    </x-ui.select>
                </div>
                <div class="w-full min-w-0 flex-1">
                    <x-ui.input name="search" label="Search" :value="request('search')" class="w-full mb-0" />
                </div>
                <div class="w-full sm:w-auto sm:flex-none">
                    <x-ui.button type="submit" color="outline">Apply</x-ui.button>
                </div>
            </form>

            @if($messages->isEmpty())
                <x-none-found item="sent SMS" search="{{ request()->get('search') }}" />
            @else
                <div class="space-y-3 md:hidden" data-sent-sms-mobile-thread-list>
                    @foreach($messages as $sms)
                        @php
                            $status = $sms->status ?: \App\Models\SentSms::STATUS_QUEUED;
                            $statusClass = match ($status) {
                                \App\Models\SentSms::STATUS_FAILED => 'text-red-700 bg-red-100 border-red-200',
                                \App\Models\SentSms::STATUS_QUEUED => 'text-amber-700 bg-amber-100 border-amber-200',
                                default => 'text-green-700 bg-green-100 border-green-200',
                            };
                            $recipientLabel = trim((string) ($sms->recipient_display_name ?? ''));
                            $recipientPhone = trim((string) ($sms->recipient_phone_display ?? $sms->recipient));
                            $recipientUserId = trim((string) ($sms->recipient_user_id ?? ''));
                            $initiatedByLabel = trim((string) ($sms->initiated_by_name ?: $sms->initiatedBy?->getName() ?? ''));
                            $messagePreview = \Illuminate\Support\Str::limit(trim((string) $sms->message), 140);
                            $errorPreview = trim((string) ($sms->error_message ?? ''));
                            $sentAt = $sms->sent_at ?? $sms->failed_at ?? $sms->created_at;
                            $threadReplies = collect(data_get($repliesBySentSmsId, (string) $sms->id, []));
                        @endphp
                        <div id="sent-sms-mobile-{{ $sms->id }}" class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm">
                            <div class="flex items-start justify-between gap-3">
                                <div class="min-w-0">
                                    <div class="text-xs font-semibold uppercase tracking-wide text-gray-500">Sent</div>
                                    <div class="whitespace-nowrap text-sm text-gray-900">{{ $sentAt?->format('M j, Y') ?? '-' }}</div>
                                    <div class="text-xs text-gray-500">{{ $sentAt?->format('g:i a') ?? '-' }}</div>
                                </div>
                                <div class="shrink-0">
                                    <span class="inline-flex items-center rounded-full border px-2 py-0.5 text-xs font-semibold text-center {{ $statusClass }}">{{ ucfirst($status) }}</span>
                                </div>
                            </div>

                            <div class="mt-4 space-y-3">
                                <div>
                                    <div class="text-xs font-semibold uppercase tracking-wide text-gray-500">To</div>
                                    @if($recipientLabel !== '' && strcasecmp($recipientLabel, $recipientPhone) !== 0)
                                        @if($recipientUserId !== '')
                                            <a href="{{ route('admin.user.edit', $recipientUserId) }}" class="mt-0.5 block whitespace-nowrap font-medium text-primary-color hover:underline">{{ $recipientLabel }}</a>
                                        @else
                                            <div class="mt-0.5 block whitespace-nowrap font-medium text-gray-900">{{ $recipientLabel }}</div>
                                        @endif
                                        <div class="text-xs text-gray-500 break-all">{{ $recipientPhone }}</div>
                                    @else
                                        <div class="mt-0.5 break-all text-sm font-medium text-gray-900">{{ $recipientPhone }}</div>
                                    @endif
                                </div>

                                <div>
                                    <div class="text-xs font-semibold uppercase tracking-wide text-gray-500">Message</div>
                                    <div class="mt-0.5 break-words text-sm text-gray-900">{{ $messagePreview }}</div>
                                    @if($sms->reference)
                                        <div class="mt-1 text-xs text-gray-500">Ref: <span class="font-mono">{{ $sms->reference }}</span></div>
                                    @endif
                                </div>

                                <div class="grid grid-cols-1 gap-2 text-xs text-gray-600 sm:grid-cols-2">
                                    <div>Origin: <span class="font-mono">{{ $sms->origin ?: '-' }}</span></div>
                                    <div>Provider ID: <span class="font-mono">{{ $sms->provider_message_id ?: '-' }}</span></div>
                                    <div class="sm:col-span-2">Initiated by: {{ $initiatedByLabel !== '' ? $initiatedByLabel : '-' }}</div>
                                    <div class="sm:col-span-2">Response: {{ $sms->response_status_label ?? '-' }}</div>
                                </div>
                            </div>

                            @if($errorPreview !== '')
                                <div class="mt-4 rounded border border-red-200 bg-red-50 px-3 py-2 text-xs text-red-700">
                                    {{ $errorPreview }}
                                </div>
                            @endif

                            @foreach($threadReplies as $reply)
                                @php
                                    $replyFrom = trim((string) data_get($reply, 'reply_from_display', '')) ?: (formatPhoneNumber((string) data_get($reply, 'originator', '')) ?: (string) data_get($reply, 'originator', ''));
                                    $isAcknowledged = (bool) data_get($reply, 'is_acknowledged', false);
                                @endphp
                                <div
                                    x-data="{
                                        acknowledged: @js($isAcknowledged),
                                        busy: false,
                                        ackUrl: @js(route('admin.server.sent-sms.replies.acknowledge', data_get($reply, 'id'))),
                                    }"
                                    class="mt-4 rounded border border-gray-200 px-3 py-3"
                                    x-bind:class="acknowledged ? 'bg-white' : 'bg-amber-50'"
                                >
                                    <div class="flex items-start justify-between gap-3">
                                        <div class="min-w-0">
                                            <div class="text-xs text-gray-500">
                                                <span class="font-semibold uppercase tracking-wide text-gray-500">↳ Reply</span>
                                                <span class="mx-1">·</span>
                                                <span>{{ data_get($reply, 'received_at')?->format('M j, Y g:i a') ?? '-' }}</span>
                                                <span class="mx-1">·</span>
                                                <span>From {{ $replyFrom }}</span>
                                                @if(data_get($reply, 'opted_out', false))
                                                    <x-ui.badge color="danger" size="xxs">Opted out</x-ui.badge>
                                                @endif
                                            </div>
                                            <div class="mt-2 break-words text-sm text-gray-900">{{ data_get($reply, 'message', '') }}</div>
                                        </div>
                                        @if(! $isAcknowledged)
                                            <form method="POST" action="{{ route('admin.server.sent-sms.replies.acknowledge', data_get($reply, 'id')) }}" class="shrink-0" x-show="! acknowledged" x-cloak x-on:submit.prevent="acknowledgeReply($data)">
                                                @csrf
                                                @method('PATCH')
                                                <x-ui.button type="submit" color="primary-outline-sm" class="gap-2" x-bind:disabled="busy">
                                                    <span x-text="busy ? 'Acknowledging...' : 'Acknowledge'"></span>
                                                </x-ui.button>
                                            </form>
                                        @endif
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endforeach
                </div>

                <div class="hidden md:block" data-sent-sms-desktop-table>
                    <x-ui.table>
                        <x-slot:header>
                            <th>Sent</th>
                            <th>To</th>
                            <th>Message</th>
                            <th class="hidden lg:table-cell">Details</th>
                            <th>Status</th>
                            <th>Response</th>
                        </x-slot:header>
                        <x-slot:body>
                            @foreach($messages as $sms)
                                @php
                                    $status = $sms->status ?: \App\Models\SentSms::STATUS_QUEUED;
                                    $statusClass = match ($status) {
                                        \App\Models\SentSms::STATUS_FAILED => 'text-red-700 bg-red-100 border-red-200',
                                        \App\Models\SentSms::STATUS_QUEUED => 'text-amber-700 bg-amber-100 border-amber-200',
                                        default => 'text-green-700 bg-green-100 border-green-200',
                                    };
                                    $recipientLabel = trim((string) ($sms->recipient_display_name ?? ''));
                                    $recipientPhone = trim((string) ($sms->recipient_phone_display ?? $sms->recipient));
                                    $recipientUserId = trim((string) ($sms->recipient_user_id ?? ''));
                                    $initiatedByLabel = trim((string) ($sms->initiated_by_name ?: $sms->initiatedBy?->getName() ?? ''));
                                    $messagePreview = \Illuminate\Support\Str::limit(trim((string) $sms->message), 140);
                                    $errorPreview = trim((string) ($sms->error_message ?? ''));
                                    $sentAt = $sms->sent_at ?? $sms->failed_at ?? $sms->created_at;
                                    $threadReplies = collect(data_get($repliesBySentSmsId, (string) $sms->id, []));
                                @endphp
                                <tr id="sent-sms-{{ $sms->id }}" class="{{ $errorPreview !== '' ? 'border-b-0' : '' }}">
                                    <td>
                                        <div class="whitespace-nowrap text-sm">{{ $sentAt?->format('M j, Y') ?? '-' }}</div>
                                        <div class="text-xs text-gray-500">{{ $sentAt?->format('g:i a') ?? '-' }}</div>
                                    </td>
                                    <td>
                                        @if($recipientLabel !== '' && strcasecmp($recipientLabel, $recipientPhone) !== 0)
                                            @if($recipientUserId !== '')
                                                <a href="{{ route('admin.user.edit', $recipientUserId) }}" class="font-medium whitespace-nowrap text-primary-color hover:underline">{{ $recipientLabel }}</a>
                                            @else
                                                <div class="font-medium whitespace-nowrap text-gray-900">{{ $recipientLabel }}</div>
                                            @endif
                                            <div class="text-xs text-gray-500 break-all">{{ $recipientPhone }}</div>
                                        @else
                                            <div class="font-medium text-gray-900 break-all">{{ $recipientPhone }}</div>
                                        @endif
                                    </td>
                                    <td>
                                        <div class="max-w-[22rem] break-words">{{ $messagePreview }}</div>
                                        @if($sms->reference)
                                            <div class="mt-1 text-xs text-gray-500">Ref: <span class="font-mono">{{ $sms->reference }}</span></div>
                                        @endif
                                    </td>
                                    <td class="hidden lg:table-cell">
                                        <div class="space-y-1 text-xs text-gray-600">
                                            <div>Origin: <span class="font-mono">{{ $sms->origin ?: '-' }}</span></div>
                                            <div>Provider ID: <span class="font-mono">{{ $sms->provider_message_id ?: '-' }}</span></div>
                                            <div>Initiated by: {{ $initiatedByLabel !== '' ? $initiatedByLabel : '-' }}</div>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="inline-flex items-center rounded-full border px-2 py-0.5 text-xs font-semibold text-center {{ $statusClass }}">{{ ucfirst($status) }}</span>
                                    </td>
                                    <td class="text-xs text-gray-700">{{ $sms->response_status_label ?? '-' }}</td>
                                </tr>
                                @if($errorPreview !== '')
                                    <tr class="border-t-0">
                                        <td colspan="6" class="pt-0 pb-4">
                                            <div class="ml-6 border-l-2 border-red-200 pl-4 text-xs text-red-700">
                                                {{ $errorPreview }}
                                            </div>
                                        </td>
                                    </tr>
                                @endif
                                @foreach($threadReplies as $reply)
                                    @php
                                        $replyFrom = trim((string) data_get($reply, 'reply_from_display', '')) ?: (formatPhoneNumber((string) data_get($reply, 'originator', '')) ?: (string) data_get($reply, 'originator', ''));
                                        $isAcknowledged = (bool) data_get($reply, 'is_acknowledged', false);
                                    @endphp
                                    <tr
                                        x-data="{
                                            acknowledged: @js($isAcknowledged),
                                            busy: false,
                                            ackUrl: @js(route('admin.server.sent-sms.replies.acknowledge', data_get($reply, 'id'))),
                                        }"
                                        x-bind:class="acknowledged ? '' : 'highlight-row'"
                                        class="border-t-0"
                                    >
                                        <td colspan="6" class="pt-0 pb-4">
                                            <div class="ml-6 border-l-2 {{ $isAcknowledged ? 'border-gray-200' : 'border-amber-300' }} pl-4 py-3">
                                                <div class="flex items-start justify-between gap-3">
                                                    <div class="min-w-0">
                                                        <div class="text-xs text-gray-500">
                                                            <span class="font-semibold uppercase tracking-wide text-gray-500">↳ Reply</span>
                                                            <span class="mx-1">·</span>
                                                            <span>{{ data_get($reply, 'received_at')?->format('M j, Y g:i a') ?? '-' }}</span>
                                                            <span class="mx-1">·</span>
                                                            <span>From {{ $replyFrom }}</span>
                                                            @if(data_get($reply, 'opted_out', false))
                                                                <x-ui.badge color="danger" size="xxs">Opted out</x-ui.badge>
                                                            @endif
                                                        </div>
                                                        <div class="mt-2 break-words text-sm text-gray-900">{{ data_get($reply, 'message', '') }}</div>
                                                    </div>
                                                    @if(! $isAcknowledged)
                                                        <form method="POST" action="{{ route('admin.server.sent-sms.replies.acknowledge', data_get($reply, 'id')) }}" class="shrink-0" x-show="! acknowledged" x-cloak x-on:submit.prevent="acknowledgeReply($data)">
                                                            @csrf
                                                            @method('PATCH')
                                                            <x-ui.button type="submit" color="primary-outline-sm" class="gap-2" x-bind:disabled="busy">
                                                                <span x-text="busy ? 'Acknowledging...' : 'Acknowledge'"></span>
                                                            </x-ui.button>
                                                        </form>
                                                    @endif
                                                </div>
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach
                            @endforeach
                        </x-slot:body>
                    </x-ui.table>
                </div>

                <div class="mt-6">
                    {{ $messages->appends(request()->query())->links() }}
                </div>
            @endif

            @if(($unmatchedReplies ?? collect())->isNotEmpty())
                <section class="mt-10">
                    <div class="mb-4 flex items-end justify-between gap-4">
                        <div>
                            <h2 class="text-lg font-semibold text-gray-900">Unmatched replies</h2>
                            <p class="mt-1 text-sm text-gray-600">Inbound SMS replies that could not be linked to a sent message.</p>
                        </div>
                        @if(($unacknowledgedReplyCount ?? 0) > 0)
                            <x-ui.badge color="warning" size="xxs">{{ $unacknowledgedReplyCount }} awaiting acknowledgement</x-ui.badge>
                        @endif
                    </div>

                    <div class="space-y-3 border-t border-gray-200 pt-4">
                        @foreach($unmatchedReplies as $reply)
                            @php
                                $replyFrom = trim((string) data_get($reply, 'reply_from_display', '')) ?: (formatPhoneNumber((string) data_get($reply, 'originator', '')) ?: (string) data_get($reply, 'originator', ''));
                                $isAcknowledged = (bool) data_get($reply, 'is_acknowledged', false);
                            @endphp
                            <div
                                x-data="{
                                    acknowledged: @js($isAcknowledged),
                                    busy: false,
                                    ackUrl: @js(route('admin.server.sent-sms.replies.acknowledge', data_get($reply, 'id'))),
                                }"
                                class="rounded-md border border-gray-200 bg-white px-4 py-3"
                                x-bind:class="acknowledged ? '' : 'bg-amber-50'"
                            >
                                <div class="flex items-start justify-between gap-3">
                                    <div class="min-w-0">
                                        <div class="text-xs text-gray-500">
                                            <span class="font-semibold uppercase tracking-wide">Reply</span>
                                            <span class="mx-1">·</span>
                                            <span>{{ data_get($reply, 'received_at')?->format('M j, Y g:i a') ?? '-' }}</span>
                                            <span class="mx-1">·</span>
                                            <span>From {{ $replyFrom }}</span>
                                            @if(data_get($reply, 'opted_out', false))
                                                <x-ui.badge color="danger" size="xxs">Opted out</x-ui.badge>
                                            @endif
                                        </div>
                                        <div class="mt-2 break-words text-sm text-gray-900">{{ data_get($reply, 'message', '') }}</div>
                                    </div>
                                    @if(! $isAcknowledged)
                                        <form method="POST" action="{{ route('admin.server.sent-sms.replies.acknowledge', data_get($reply, 'id')) }}" class="shrink-0" x-show="! acknowledged" x-cloak x-on:submit.prevent="acknowledgeReply($data)">
                                            @csrf
                                            @method('PATCH')
                                            <x-ui.button type="submit" color="primary-outline-sm" class="gap-2" x-bind:disabled="busy">
                                                <span x-text="busy ? 'Acknowledging...' : 'Acknowledge'"></span>
                                            </x-ui.button>
                                        </form>
                                    @endif
                                </div>
                            </div>
                            @endforeach
                    </div>
                </section>
            @endif
            <div
                x-cloak
                x-show="sendSmsOpen"
                class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4"
                role="dialog"
                aria-modal="true"
                aria-labelledby="sent-sms-modal-title"
                @click.self="sendSmsOpen = false"
            >
                <div class="w-full max-w-2xl rounded-md bg-white shadow-deep">
                    <form method="POST" action="{{ route('admin.server.sent-sms.store') }}" class="flex flex-col">
                        @csrf
                        <div class="flex items-center justify-between border-b border-gray-200 px-4 py-3">
                            <h2 id="sent-sms-modal-title" class="text-sm font-semibold text-gray-900">Send SMS</h2>
                            <button type="button" class="inline-flex h-8 w-8 items-center justify-center rounded border border-gray-300 text-gray-600 hover:bg-gray-50" aria-label="Close SMS dialog" x-on:click="sendSmsOpen = false">
                                &times;
                            </button>
                        </div>

                        <div class="space-y-4 p-4">
                            <div class="grid gap-4 md:grid-cols-2">
                                <div class="min-w-0">
                                    <x-ui.input
                                        name="recipient"
                                        label="To"
                                        :value="old('recipient')"
                                        info="Multiple numbers can be used, separated by ;"
                                        placeholder="+61400111222; +61400987654"
                                        x-on:input="smsRecipients = $event.target.value; recipientError = ''"
                                        x-on:blur="validateRecipients($event.target)"
                                    />
                                    <div x-cloak x-show="recipientError !== ''" class="-mt-2 text-xs text-red-600" x-text="recipientError"></div>
                                </div>
                                <x-ui.input
                                    name="reference"
                                    label="Reference (optional)"
                                    :value="old('reference')"
                                />
                            </div>

                            <div>
                                <label for="sent-sms-message" class="block text-sm pl-1">Message</label>
                                <textarea
                                    id="sent-sms-message"
                                    name="message"
                                    rows="6"
                                    class="mt-1 block w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900 focus:border-indigo-300 focus:outline-none focus:ring-0"
                                    x-model="smsMessage"
                                >{{ old('message') }}</textarea>
                            </div>
                            <div class="flex items-center justify-between gap-3 text-xs text-gray-500">
                                <div x-text="smsStats.label"></div>
                            </div>
                        </div>

                        <div class="flex items-center justify-end gap-2 border-t border-gray-200 px-4 py-3">
                            <x-ui.button type="button" color="outline" x-on:click.prevent="sendSmsOpen = false">Cancel</x-ui.button>
                            <x-ui.button type="submit" class="gap-3">
                                <i class="fa-solid fa-paper-plane"></i>
                                Send
                            </x-ui.button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </x-container>
</x-layout>
