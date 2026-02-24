<x-layout>
    <x-mast>Audit Log</x-mast>

    <x-container>
        <x-ui.toolbar>
            <x-slot:left>
                <form method="GET" action="{{ route('admin.server.audit') }}" class="flex items-end gap-3">
                    <div>
                        <x-ui.select name="event" label="Event">
                            <option value="">All Events</option>
                            @foreach($events as $event)
                                <option value="{{ $event }}" @selected(request()->query('event') === $event)>{{ ucfirst($event) }}</option>
                            @endforeach
                        </x-ui.select>
                    </div>
                    <div class="mb-4">
                        <x-ui.button type="submit" color="outline">Filter</x-ui.button>
                    </div>
                </form>
            </x-slot:left>
            <x-slot:right>
                <x-ui.search name="search" label="Search" />
            </x-slot:right>
        </x-ui.toolbar>

        <form method="GET" class="my-4 bg-white border border-gray-200 rounded-lg shadow-sm p-4 flex flex-wrap items-end gap-3">
            <div class="ml-auto grid grid-cols-1 md:grid-cols-3 gap-3 text-xs text-gray-600">
                <div>
                    <div class="font-semibold text-gray-700">Audit Table Size</div>
                    <div>{{ $auditMeta['table_size_human'] ?? 'Unavailable' }}</div>
                </div>
                <div>
                    <div class="font-semibold text-gray-700">Oldest Record</div>
                    <div>{{ $auditMeta['oldest_record_at'] ? \Carbon\Carbon::parse($auditMeta['oldest_record_at'])->format('M j, Y g:i a') : 'No records' }}</div>
                </div>
                <div>
                    <div class="font-semibold text-gray-700">Total Records</div>
                    <div>{{ number_format((int) ($auditMeta['total_records'] ?? 0)) }}</div>
                </div>
            </div>
        </form>

        <form id="audit-prune-form" method="POST" action="{{ route('admin.server.audit.prune') }}" class="mb-4 bg-white border border-gray-200 rounded-lg shadow-sm p-4 flex flex-wrap items-end gap-3">
            @csrf
            <input type="hidden" name="event" value="{{ (string) request()->query('event', '') }}">
            <input type="hidden" name="search" value="{{ (string) request()->query('search', '') }}">
            <div class="w-44">
                <x-ui.select label="Prune Older Than" name="prune_days">
                    <option value="30">30 days</option>
                    <option value="60">60 days</option>
                    <option value="90" selected>90 days</option>
                    <option value="180">180 days</option>
                    <option value="365">365 days</option>
                </x-ui.select>
            </div>
            <div class="mb-4">
                <x-ui.button type="button" color="danger-outline" x-data x-on:click.prevent="confirmAuditPrune()">Prune Records</x-ui.button>
            </div>
        </form>

        @if($logs->isEmpty())
            <x-none-found item="audit logs" search="{{ request()->get('search') }}" />
        @else
            <x-ui.table>
                <x-slot:header>
                    <th>When</th>
                    <th class="whitespace-nowrap">Event</th>
                    <th class="whitespace-nowrap">Model / Record</th>
                    <th>Actor</th>
                    <th class="hidden lg:table-cell">Request</th>
                    <th class="hidden xl:table-cell text-center">Changes</th>
                </x-slot:header>
                <x-slot:body>
                    @foreach($logs as $log)
                        @php
                            $modelShort = class_basename((string) $log->auditable_type);
                            $actorLabel = $log->actor?->email
                                ? ($log->actor->getName() . ' <' . $log->actor->email . '>')
                                : 'System';
                            $changes = [
                                'old' => $log->old_values,
                                'new' => $log->new_values,
                            ];
                        @endphp
                        <tr>
                            <td>
                                <div class="whitespace-nowrap">
                                    @if($log->created_at)
                                        {{ $log->created_at->format('M j, Y')}}<br>{{$log->created_at->format('g:i a') }}
                                    @else
                                        -
                                    @endif
                                </div>
                                <div class="text-xs text-gray-500">{{ $log->created_at?->diffForHumans() ?? '' }}</div>
                            </td>
                            <td class="whitespace-nowrap"><span class="uppercase text-xxs font-semibold whitespace-nowrap">{{ $log->event }}</span></td>
                            <td class="whitespace-nowrap" style="white-space: nowrap; overflow-wrap: normal; word-break: normal;">{{ $modelShort }}<br>ID: {{ $log->auditable_id }}</td>
                            <td>
                                <div>{{ $actorLabel }}</div>
                                @if($log->ip_address)
                                    <div class="text-xs text-gray-500">IP: {{ $log->ip_address }}</div>
                                @endif
                            </td>
                            <td class="hidden lg:table-cell">
                                @if($log->url)
                                    <div class="max-w-xs break-all text-xs text-gray-600">{{ $log->url }}</div>
                                @else
                                    -
                                @endif
                            </td>
                            <td class="hidden xl:table-cell text-center">
                                <button
                                    type="button"
                                    class="audit-log-view inline-flex items-center rounded border border-gray-300 px-2 py-1 text-xs font-medium text-gray-700 hover:bg-gray-50"
                                    data-title="Changes for {{ $modelShort }} {{ $log->auditable_id }}"
                                    data-content-id="audit-changes-{{ $log->id }}"
                                >
                                    View
                                </button>
                                <template id="audit-changes-{{ $log->id }}">
                                    <div class="max-h-[70vh] overflow-y-auto text-left">
                                        <div class="mb-3 text-xs text-gray-500">Event: {{ strtoupper((string) $log->event) }}</div>
                                        <div class="mb-2 text-xs font-semibold uppercase tracking-wide text-gray-700">Old Values</div>
                                        <pre class="mb-4 overflow-x-auto rounded bg-gray-50 p-3 text-xxs text-gray-700">{{ json_encode($changes['old'] ?? new stdClass(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>
                                        <div class="mb-2 text-xs font-semibold uppercase tracking-wide text-gray-700">New Values</div>
                                        <pre class="overflow-x-auto rounded bg-gray-50 p-3 text-xxs text-gray-700">{{ json_encode($changes['new'] ?? new stdClass(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>
                                    </div>
                                </template>
                            </td>
                        </tr>
                    @endforeach
                </x-slot:body>
            </x-ui.table>

            {{ $logs->appends(request()->query())->links() }}
        @endif
    </x-container>

    <div id="audit-log-modal" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/50 p-4" aria-hidden="true">
        <div class="w-full max-w-5xl rounded-md bg-white shadow-deep">
            <div class="flex items-center justify-between border-b border-gray-200 px-4 py-3">
                <h2 id="audit-log-modal-title" class="text-sm font-semibold text-gray-900">Audit Changes</h2>
                <button
                    type="button"
                    id="audit-log-modal-close"
                    class="inline-flex h-8 w-8 items-center justify-center rounded border border-gray-300 text-gray-600 hover:bg-gray-50"
                    aria-label="Close changes modal"
                >
                    &times;
                </button>
            </div>
            <div id="audit-log-modal-body" class="max-h-[75vh] overflow-y-auto p-4">
                <div class="text-sm text-gray-600">No changes recorded.</div>
            </div>
            <div class="border-t border-gray-200 px-4 py-3 text-right">
                <x-ui.button type="button" color="outline" id="audit-log-modal-close-footer">Close</x-ui.button>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const modal = document.getElementById('audit-log-modal');
            const modalTitle = document.getElementById('audit-log-modal-title');
            const modalBody = document.getElementById('audit-log-modal-body');
            const closeButton = document.getElementById('audit-log-modal-close');
            const closeFooterButton = document.getElementById('audit-log-modal-close-footer');

            if (!modal || !modalTitle || !modalBody) {
                return;
            }

            const closeModal = () => {
                modal.classList.add('hidden');
                modal.classList.remove('flex');
                modal.setAttribute('aria-hidden', 'true');
                document.body.classList.remove('overflow-hidden');
            };

            const openModal = (title, html) => {
                modalTitle.textContent = title;
                modalBody.innerHTML = html;
                modal.classList.remove('hidden');
                modal.classList.add('flex');
                modal.setAttribute('aria-hidden', 'false');
                document.body.classList.add('overflow-hidden');
            };

            document.querySelectorAll('.audit-log-view').forEach((button) => {
                button.addEventListener('click', () => {
                    const title = button.getAttribute('data-title') || 'Audit Changes';
                    const templateId = button.getAttribute('data-content-id');
                    const template = templateId ? document.getElementById(templateId) : null;
                    const html = template ? template.innerHTML : '<div class="text-sm text-gray-600">No changes recorded.</div>';
                    openModal(title, html);
                });
            });

            closeButton?.addEventListener('click', closeModal);
            closeFooterButton?.addEventListener('click', closeModal);

            modal.addEventListener('click', (event) => {
                if (event.target === modal) {
                    closeModal();
                }
            });

            document.addEventListener('keydown', (event) => {
                if (event.key === 'Escape' && !modal.classList.contains('hidden')) {
                    closeModal();
                }
            });
        });

        function confirmAuditPrune() {
            const form = document.getElementById('audit-prune-form');
            if (!form || !window.SM || typeof window.SM.confirm !== 'function') {
                form && form.submit();
                return;
            }

            const select = form.querySelector('select[name="prune_days"]');
            const selectedLabel = select && select.options[select.selectedIndex]
                ? select.options[select.selectedIndex].text
                : 'the selected period';

            window.SM.confirm(
                'Confirm prune',
                `Delete audit records older than ${selectedLabel}? This cannot be undone.`,
                'Prune',
                (isConfirmed) => {
                    if (!isConfirmed) {
                        return;
                    }
                    form.submit();
                }
            );
        }
    </script>
</x-layout>
