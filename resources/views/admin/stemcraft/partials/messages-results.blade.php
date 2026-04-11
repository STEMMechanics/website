@if($messages->isEmpty())
    <section class="rounded-3xl border border-dashed border-gray-300 bg-white p-6">
        <h2 class="text-lg font-semibold text-gray-900">No messages found</h2>
        <p class="mt-2 text-sm leading-6 text-gray-600">No STEMCraft messages match the current filters.</p>
    </section>
@else
    <div class="space-y-4 lg:hidden">
                @foreach($messages as $message)
            <section class="rounded-3xl border border-gray-200 bg-white p-5 shadow-sm {{ (string) request('highlight') === (string) $message->id ? 'ring-2 ring-amber-300' : '' }}">
                <div class="flex flex-wrap items-center gap-3">
                    <x-ui.badge color="gray" size="xxs" uppercase="true">{{ $message->message_type }}</x-ui.badge>
                    <x-ui.badge :color="$message->passed ? 'success' : 'warning'" size="xxs">{{ $message->passed ? 'Passed' : 'Blocked' }}</x-ui.badge>
                    <span class="text-xs text-gray-500">#{{ $message->id }}</span>
                </div>

                <div class="mt-3">
                    <div class="text-sm font-semibold text-gray-900">{{ $message->username }}</div>
                    <div class="text-xs font-mono text-gray-500 break-all">{{ $message->uuid }}</div>
                </div>

                <div class="mt-4 rounded-2xl bg-gray-50 px-4 py-3">
                    <div class="text-xs font-semibold uppercase tracking-wide text-gray-500">Displayed message</div>
                    <div class="mt-1 text-sm text-gray-900 whitespace-pre-wrap">{{ $message->displayMessage() }}</div>
                </div>

                <div class="mt-3 rounded-2xl bg-gray-50 px-4 py-3">
                    <div class="text-xs font-semibold uppercase tracking-wide text-gray-500">Raw message</div>
                    <div class="mt-1 text-sm text-gray-900 whitespace-pre-wrap">{{ $message->raw_message }}</div>
                </div>

                <div class="mt-3 grid gap-3 sm:grid-cols-2">
                    <div>
                        <div class="text-xs font-semibold uppercase tracking-wide text-gray-500">Occurred</div>
                        <div class="mt-1 text-sm text-gray-900">{{ $message->occurred_at?->format('j M Y g:i a') ?? '-' }}</div>
                    </div>
                    <div>
                        <div class="text-xs font-semibold uppercase tracking-wide text-gray-500">Location</div>
                        <div class="mt-1 text-sm text-gray-900">{{ $message->formattedLocation() }}</div>
                    </div>
                </div>

                @if(! $message->passed)
                    <div class="mt-3 text-sm text-amber-900">Reason: {{ $message->failureSummary() }}</div>
                @endif

                @if($message->context)
                    <details class="mt-4 rounded-2xl bg-gray-50 px-4 py-3" data-refresh-key="message-{{ $message->id }}-mobile-context">
                        <summary class="cursor-pointer text-sm font-semibold text-gray-900">View context</summary>
                        <pre class="mt-3 overflow-auto rounded-xl bg-white p-3 text-xs text-gray-800 whitespace-pre-wrap">{{ json_encode($message->context, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>
                    </details>
                @endif
            </section>
        @endforeach
    </div>

    <div class="hidden lg:block">
        <x-ui.table>
            <x-slot:header>
                <th>Time</th>
                <th>Player</th>
                <th>Type</th>
                <th>Result</th>
                <th>Displayed</th>
                <th class="hidden xl:table-cell">Location</th>
            </x-slot:header>
            <x-slot:body>
                @foreach($messages as $message)
                    <tr class="{{ (string) request('highlight') === (string) $message->id ? 'bg-amber-50/80' : '' }}">
                        <td class="whitespace-nowrap">{{ $message->occurred_at?->format('j M Y g:i a') ?? '-' }}</td>
                        <td>
                            <div class="font-semibold text-gray-900">{{ $message->username }}</div>
                            <div class="text-xs font-mono text-gray-500 break-all">{{ $message->uuid }}</div>
                            @if($message->account?->user)
                                <div class="mt-1 text-xs text-gray-600">{{ $message->account->user->getName() ?: $message->account->user->email }}</div>
                            @endif
                        </td>
                        <td class="whitespace-nowrap">
                            <x-ui.badge color="gray" size="xxs" uppercase="true">{{ $message->message_type }}</x-ui.badge>
                            <div class="text-xs text-gray-500">{{ $message->server_name }}</div>
                        </td>
                        <td>
                            <x-ui.badge :color="$message->passed ? 'success' : 'warning'" size="xxs">{{ $message->passed ? 'Passed' : 'Blocked' }}</x-ui.badge>
                            @if(! $message->passed)
                                <div class="mt-1 text-xs text-gray-600">{{ $message->failureSummary() }}</div>
                            @endif
                        </td>
                        <td>
                            <div class="text-sm text-gray-900 whitespace-pre-wrap">{{ $message->displayMessage() }}</div>
                            <details class="mt-3" data-refresh-key="message-{{ $message->id }}-desktop-raw">
                                <summary class="cursor-pointer text-xs font-semibold text-gray-700">Admin raw view</summary>
                                <pre class="mt-2 max-h-40 overflow-auto rounded-xl bg-gray-50 p-3 text-xs text-gray-800 whitespace-pre-wrap">{{ $message->raw_message }}</pre>
                                @if($message->context)
                                    <div class="mt-2 text-xs font-semibold uppercase tracking-wide text-gray-500">Context</div>
                                    <pre class="mt-1 max-h-40 overflow-auto rounded-xl bg-gray-50 p-3 text-xs text-gray-800 whitespace-pre-wrap">{{ json_encode($message->context, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>
                                @endif
                            </details>
                        </td>
                        <td class="hidden xl:table-cell">{{ $message->formattedLocation() }}</td>
                    </tr>
                @endforeach
            </x-slot:body>
        </x-ui.table>
    </div>

    {{ $messages->appends(request()->query())->links() }}
@endif
