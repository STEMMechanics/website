<x-layout>
    <x-mast>Media</x-mast>

    <x-container>
        @if(isset($filteredOwner) && $filteredOwner)
            <div class="mb-4 rounded-lg border border-sky-200 bg-sky-50 px-4 py-3 text-sm text-sky-900">
                Showing media for <strong>{{ $filteredOwner->username ?: $filteredOwner->email ?: $filteredOwner->getName() }}</strong>.
                <a href="{{ route('admin.media.index') }}" class="ml-2 text-primary-color hover:underline">Clear filter</a>
            </div>
        @endif
        <x-ui.toolbar>
            <x-slot:left>
                <x-ui.button href="{{ route('admin.media.create') }}">Create</x-ui.button>
                <x-ui.button type="button" color="outline" class="ml-2" id="regenerate-missing-variants-button" x-data x-on:click.prevent="confirmRegenerateMissingVariants()">Regenerate Missing Variants</x-ui.button>
            </x-slot:left>
            <x-slot:right>
                <x-ui.search name="search" label="Search" />
            </x-slot:right>
        </x-ui.toolbar>
        <div id="regenerate-missing-variants-status" class="hidden mb-4 rounded border border-gray-200 bg-gray-50 px-3 py-2">
            <div class="mb-1 flex items-center justify-between">
                <div class="text-sm font-semibold text-gray-700" id="regenerate-missing-variants-status-title">Regenerating Missing Variants</div>
                <div class="text-xs text-gray-600" id="regenerate-missing-variants-status-percent">0%</div>
            </div>
            <div class="h-2 w-full overflow-hidden rounded bg-gray-200">
                <div id="regenerate-missing-variants-status-bar" class="h-2 rounded bg-primary-color transition-all duration-300" style="width:0"></div>
            </div>
            <div class="mt-1 text-xs text-gray-600" id="regenerate-missing-variants-status-meta"></div>
            <div id="regenerate-missing-variants-status-errors" class="mt-3 hidden">
                <div class="text-xs font-semibold text-red-700">Processing errors</div>
                <ul id="regenerate-missing-variants-status-errors-list" class="mt-1 space-y-1 text-xs"></ul>
            </div>
        </div>

        @if($media->isEmpty())
            <x-none-found item="media" search="{{ request()->get('search') }}" />
        @else
            <x-ui.table>
                <x-slot:header>
                    <th>Title</th>
                    <th class="hidden lg:table-cell">Owner</th>
                    <th class="hidden md:table-cell">Type</th>
                    <th class="hidden md:table-cell">Size</th>
                    <th class="hidden md:table-cell">Uploaded</th>
                    <th>Action</th>
                </x-slot:header>
                <x-slot:body>
                    @foreach ($media as $medium)
                        <tr>
                            <td>
                                <div class="flex items-center">
                                    <div class="relative mr-3 shrink-0">
                                        <img src="{{ $medium->thumbnail }}" class="max-h-12 max-w-12 -ml-2 -my-3 inline rounded" alt="{{ $medium->title }}" {{ in_array($medium->status, ['processing', 'queued'], true) ? 'data-thumbnail=' . $medium->name : '' }} />
                                        @if($medium->is_private)
                                            <span class="absolute -left-2 -top-2 inline-flex h-5 w-5 items-center justify-center rounded-full bg-slate-700 text-white" title="Private media">
                                                <i class="fa-solid fa-user-lock text-[10px]"></i>
                                            </span>
                                        @endif
                                    </div>
                                    <div>
                                        <a href="{{ route('admin.media.edit', $medium) }}" class="whitespace-normal font-semibold text-gray-900 hover:text-primary-color">
                                            {{ $medium->title }}
                                        </a>{!! $medium->password !== null ? '<i class="fa-solid fa-lock text-xs text-gray-400 ml-0.5 -translate-y-1.5 scale-75"></i>': '' !!}
                                        <div class="md:hidden text-xs text-gray-500">{{ $medium->file_type }}</div>
                                        <div class="lg:hidden text-xs text-gray-500">{{ $medium->user?->username ?: $medium->user?->email ?: 'Unassigned' }}</div>
                                        <div class="md:hidden text-xs text-gray-500">{{ \Carbon\Carbon::parse($medium->created_at)->format('j/m/Y') }} - {{ \App\Helpers::bytesToString($medium->size) }}</div>
                                    </div>
                                </div>
                            </td>
                            <td class="hidden lg:table-cell">{{ $medium->user?->username ?: $medium->user?->email ?: 'Unassigned' }}</td>
                            <td class="hidden md:table-cell">{{ $medium->file_type }}</td>
                            <td class="hidden md:table-cell">{{ \App\Helpers::bytesToString($medium->size) }}</td>
                            <td class="hidden md:table-cell">{{ \Carbon\Carbon::parse($medium->created_at)->format('M j Y, g:i a') }}</td>
                            <td>
                                <div class="flex justify-center gap-3">
                                    <a href="{{ route('admin.media.edit', $medium) }}" title="Edit media item" class="hover:text-primary-color"><i class="fa-solid fa-pen-to-square"></i></a>
                                    <a href="#" class="hover:text-primary-color" title="Copy media link" x-data x-on:click.prevent="SM.copyToClipboard('{{ $medium->url }}')"><i class="fa-solid fa-link"></i></a>
                                    <a href="{{ $medium->url }}?download" class="hover:text-primary-color" title="Download media"><i class="fa-solid fa-download"></i></a>
                                    <a href="#" class="hover:text-red-600" title="Delete media item" x-data x-on:click.prevent="SM.confirmDelete('{{ csrf_token() }}', 'Delete media?', 'Are you sure you want to delete this media? This action cannot be undone', '{{ route('admin.media.destroy', $medium) }}')"><i class="fa-solid fa-trash"></i></a>
                                </div>
                            </td>
                        </tr>
                  @endforeach
                </x-slot:body>
            </x-ui.table>

            {{ $media->appends(request()->query())->links() }}
        @endif

    </x-container>
</x-layout>

<script>
    const regenerateMissingVariantsState = {
        startUrl: @json(route('admin.media.regenerate-missing-variants')),
        statusUrl: @json(route('admin.media.regenerate-missing-variants.status')),
        csrf: @json(csrf_token()),
        initial: @json($missingVariantRegeneration ?? ['running' => false]),
        pollTimer: null,
    };

    function regenerateMissingVariantsElements() {
        return {
            button: document.getElementById('regenerate-missing-variants-button'),
            panel: document.getElementById('regenerate-missing-variants-status'),
            percent: document.getElementById('regenerate-missing-variants-status-percent'),
            bar: document.getElementById('regenerate-missing-variants-status-bar'),
            meta: document.getElementById('regenerate-missing-variants-status-meta'),
            title: document.getElementById('regenerate-missing-variants-status-title'),
            errorsWrap: document.getElementById('regenerate-missing-variants-status-errors'),
            errorsList: document.getElementById('regenerate-missing-variants-status-errors-list'),
        };
    }

    function renderRegenerateMissingVariantsErrors(errors) {
        const elements = regenerateMissingVariantsElements();
        if (!elements.errorsWrap || !elements.errorsList) {
            return;
        }

        const items = Array.isArray(errors) ? errors : [];
        elements.errorsList.innerHTML = '';
        if (items.length === 0) {
            elements.errorsWrap.classList.add('hidden');
            return;
        }

        elements.errorsWrap.classList.remove('hidden');
        items.forEach((item) => {
            const li = document.createElement('li');
            li.className = 'rounded border border-red-200 bg-red-50 px-2 py-1';

            const link = document.createElement('a');
            link.href = String(item.edit_url || '#');
            link.className = 'font-semibold text-red-800 hover:underline';
            link.textContent = String(item.title || item.name || 'Media item');
            if (link.href !== '#') {
                link.target = '_blank';
                link.rel = 'noopener noreferrer';
            }

            const message = document.createElement('div');
            message.className = 'mt-0.5 text-red-700 whitespace-pre-line';
            message.textContent = String(item.message || 'Unknown processing error');

            li.appendChild(link);
            li.appendChild(message);
            elements.errorsList.appendChild(li);
        });
    }

    function updateRegenerateMissingVariantsUI(status) {
        const elements = regenerateMissingVariantsElements();
        if (!elements.button || !elements.panel) {
            return;
        }

        const running = !!(status && status.running);
        elements.button.disabled = running;
        elements.button.classList.toggle('opacity-60', running);
        elements.button.classList.toggle('cursor-not-allowed', running);

        if (!status || (!running && !status.finished && !status.cancelled)) {
            elements.panel.classList.add('hidden');
            renderRegenerateMissingVariantsErrors([]);
            return;
        }

        elements.panel.classList.remove('hidden');
        const progress = Number.isFinite(Number(status.progress)) ? Number(status.progress) : 0;
        const processed = Number.isFinite(Number(status.processed_jobs)) ? Number(status.processed_jobs) : 0;
        const total = Number.isFinite(Number(status.total_jobs)) ? Number(status.total_jobs) : 0;
        const pending = Number.isFinite(Number(status.pending_jobs)) ? Number(status.pending_jobs) : 0;
        const failed = Number.isFinite(Number(status.failed_jobs)) ? Number(status.failed_jobs) : 0;
        elements.percent.textContent = `${progress}%`;
        elements.bar.style.width = `${Math.max(0, Math.min(100, progress))}%`;
        renderRegenerateMissingVariantsErrors(status.errors);

        if (running) {
            elements.title.textContent = 'Regenerating Missing Variants';
            elements.meta.textContent = `${processed} of ${total} processed, ${pending} pending${failed > 0 ? `, ${failed} failed` : ''}`;
            return;
        }

        if (status.finished) {
            elements.title.textContent = 'Missing Variant Regeneration Complete';
            elements.meta.textContent = `${processed} of ${total} processed${failed > 0 ? `, ${failed} failed` : ''}`;
            return;
        }

        if (status.cancelled) {
            elements.title.textContent = 'Missing Variant Regeneration Cancelled';
            elements.meta.textContent = `${processed} of ${total} processed${failed > 0 ? `, ${failed} failed` : ''}`;
        }
    }

    async function fetchRegenerateMissingVariantsStatus() {
        const response = await fetch(regenerateMissingVariantsState.statusUrl, {
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
            credentials: 'same-origin',
        });

        if (!response.ok) {
            throw new Error('Failed to load regeneration status');
        }

        const status = await response.json();
        updateRegenerateMissingVariantsUI(status);

        if (!status.running && regenerateMissingVariantsState.pollTimer) {
            clearInterval(regenerateMissingVariantsState.pollTimer);
            regenerateMissingVariantsState.pollTimer = null;
        }
    }

    function ensureRegenerateMissingVariantsPolling() {
        if (regenerateMissingVariantsState.pollTimer) {
            return;
        }

        regenerateMissingVariantsState.pollTimer = setInterval(() => {
            fetchRegenerateMissingVariantsStatus().catch(() => {
                /* empty */
            });
        }, 3000);
    }

    async function startRegenerateMissingVariants() {
        const response = await fetch(regenerateMissingVariantsState.startUrl, {
            method: 'POST',
            headers: {
                'Accept': 'application/json',
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': regenerateMissingVariantsState.csrf,
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: JSON.stringify({}),
            credentials: 'same-origin',
        });

        const data = await response.json();
        if (!response.ok || data.ok === false) {
            throw new Error(data.message || 'Could not queue regeneration');
        }

        if (data.regeneration && data.regeneration.running) {
            updateRegenerateMissingVariantsUI(data.regeneration);
            ensureRegenerateMissingVariantsPolling();
            return;
        }

        await fetchRegenerateMissingVariantsStatus();
    }

    document.addEventListener('DOMContentLoaded', () => {
        updateRegenerateMissingVariantsUI(regenerateMissingVariantsState.initial);
        if (regenerateMissingVariantsState.initial && regenerateMissingVariantsState.initial.running) {
            ensureRegenerateMissingVariantsPolling();
        }

        fetchRegenerateMissingVariantsStatus().catch(() => {
            /* empty */
        });
    });

    function confirmRegenerateMissingVariants() {
        const doStart = () => {
            startRegenerateMissingVariants().catch((error) => {
                const message = error && error.message ? error.message : 'Could not queue regeneration';
                if (window.SM && typeof window.SM.notice === 'function') {
                    window.SM.notice('Regeneration failed', message, 'danger');
                }
            });
        };

        if (!window.SM || typeof window.SM.confirm !== 'function') {
            return;
        }

        window.SM.confirm(
            'Regenerate missing variants',
            'Queue regeneration for missing variants only? Existing variants will not be replaced.',
            'Queue Regeneration',
            (isConfirmed) => {
                if (!isConfirmed) {
                    return;
                }
                doStart();
            }
        );
    }
</script>
