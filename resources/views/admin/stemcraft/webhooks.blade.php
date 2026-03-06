<x-layout>
    <x-mast title="STEMCraft" :tabs="[
        ['title' => 'Accounts', 'route' => route('admin.stemcraft.index')],
        ['title' => 'Punishments', 'route' => route('admin.stemcraft.punishments.index')],
        ['title' => 'Messaging', 'route' => route('admin.stemcraft.messages.index')],
        ['title' => 'Webhooks', 'route' => route('admin.stemcraft.webhooks.index')],
        ['title' => 'Management', 'route' => route('admin.stemcraft.management.index')],
    ]" />

    <x-container class="mt-8" inner-class="flex flex-col gap-8">
        <section class="rounded-3xl border border-gray-200 bg-white p-5 shadow-sm sm:p-6">
            <div class="max-w-3xl">
                <h2 class="text-xl font-semibold text-gray-900">Webhook activity</h2>
                <p class="mt-2 text-sm leading-6 text-gray-600">Track what the website has sent to the Minecraft server, what the website has received back from the server, whether delivery succeeded, and when retries are queued.</p>
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

        <div id="stemcraft-webhooks-results">
            @include('admin.stemcraft.partials.webhooks-results', [
                'webhookLogs' => $webhookLogs,
            ])
        </div>
    </x-container>

    @push('scripts')
        <script>
            document.addEventListener('DOMContentLoaded', () => {
                const results = document.getElementById('stemcraft-webhooks-results');
                const snapshotUrl = @js(route('admin.stemcraft.webhooks.snapshot', request()->query()));

                if (!results || !snapshotUrl) {
                    return;
                }

                const buildSnapshotUrl = () => {
                    const url = new URL(snapshotUrl, window.location.origin);
                    url.searchParams.set('_refresh', Date.now().toString());

                    return url.toString();
                };

                const captureOpenDetailKeys = (container) => {
                    return Array.from(container.querySelectorAll('details[data-refresh-key][open]'))
                        .map((detail) => detail.dataset.refreshKey || '')
                        .filter((key) => key !== '');
                };

                const restoreOpenDetailKeys = (container, openDetailKeys) => {
                    if (!Array.isArray(openDetailKeys) || openDetailKeys.length === 0) {
                        return;
                    }

                    const keySet = new Set(openDetailKeys);
                    container.querySelectorAll('details[data-refresh-key]').forEach((detail) => {
                        const key = detail.dataset.refreshKey || '';
                        if (keySet.has(key)) {
                            detail.open = true;
                        }
                    });
                };

                const updateWebhookRows = (currentRows, nextRows) => {
                    if (currentRows.length === 0 || nextRows.length === 0) {
                        return false;
                    }

                    const currentParent = currentRows[0]?.parentElement;
                    if (!(currentParent instanceof HTMLElement)) {
                        return false;
                    }

                    const currentByKey = new Map(
                        currentRows
                            .map((row) => [(row.dataset.webhookRowKey || '').trim(), row])
                            .filter(([key]) => key !== '')
                    );

                    let changed = false;

                    nextRows.forEach((nextRow) => {
                        const key = (nextRow.dataset.webhookRowKey || '').trim();
                        if (key === '') {
                            return;
                        }

                        const existingRow = currentByKey.get(key);
                        let rowNode = existingRow;

                        if (existingRow) {
                            if (existingRow.outerHTML !== nextRow.outerHTML) {
                                rowNode = nextRow.cloneNode(true);
                                existingRow.replaceWith(rowNode);
                                changed = true;
                            }
                        } else {
                            rowNode = nextRow.cloneNode(true);
                            changed = true;
                        }

                        if (rowNode instanceof HTMLElement) {
                            currentParent.appendChild(rowNode);
                        }

                        currentByKey.delete(key);
                    });

                    currentByKey.forEach((row) => {
                        row.remove();
                        changed = true;
                    });

                    return changed;
                };

                const replaceWebhookResultsIncrementally = (container, nextHtml) => {
                    const html = typeof nextHtml === 'string' ? nextHtml : '';

                    if (container.innerHTML.trim() === html.trim()) {
                        return false;
                    }

                    const nextContainer = document.createElement('div');
                    nextContainer.innerHTML = html;

                    const currentRows = Array.from(container.querySelectorAll('[data-webhook-row-list][data-webhook-row-key]'));
                    const nextRows = Array.from(nextContainer.querySelectorAll('[data-webhook-row-list][data-webhook-row-key]'));

                    if (currentRows.length === 0 || nextRows.length === 0) {
                        return SM.replaceHtmlPreservingState(container, html);
                    }

                    const openDetailKeys = captureOpenDetailKeys(container);

                    const currentMobileRows = currentRows.filter((row) => row.dataset.webhookRowList === 'mobile');
                    const currentDesktopRows = currentRows.filter((row) => row.dataset.webhookRowList === 'desktop');
                    const nextMobileRows = nextRows.filter((row) => row.dataset.webhookRowList === 'mobile');
                    const nextDesktopRows = nextRows.filter((row) => row.dataset.webhookRowList === 'desktop');

                    const mobileChanged = updateWebhookRows(currentMobileRows, nextMobileRows);
                    const desktopChanged = updateWebhookRows(currentDesktopRows, nextDesktopRows);

                    if (!mobileChanged && !desktopChanged) {
                        return false;
                    }

                    const currentPagination = container.querySelector('[data-webhook-pagination]');
                    const nextPagination = nextContainer.querySelector('[data-webhook-pagination]');
                    if (currentPagination instanceof HTMLElement && nextPagination instanceof HTMLElement) {
                        if (currentPagination.innerHTML.trim() !== nextPagination.innerHTML.trim()) {
                            currentPagination.innerHTML = nextPagination.innerHTML;
                        }
                    } else if (currentPagination || nextPagination) {
                        return SM.replaceHtmlPreservingState(container, html);
                    }

                    restoreOpenDetailKeys(container, openDetailKeys);

                    return true;
                };

                const refresh = async () => {
                    try {
                        const response = await fetch(buildSnapshotUrl(), {
                            headers: {
                                Accept: 'application/json',
                                'X-Requested-With': 'XMLHttpRequest',
                            },
                            credentials: 'same-origin',
                            cache: 'no-store',
                        });

                        if (!response.ok) {
                            return;
                        }

                        const payload = await response.json();
                        const replaced = replaceWebhookResultsIncrementally(results, payload?.resultsHtml || '');

                        if (replaced && window.Alpine?.initTree) {
                            window.Alpine.initTree(results);
                        }
                    } catch (_error) {
                    }
                };

                window.setInterval(() => {
                    if (document.visibilityState === 'visible') {
                        refresh();
                    }
                }, 15000);

                document.addEventListener('visibilitychange', () => {
                    if (document.visibilityState === 'visible') {
                        refresh();
                    }
                });
            });
        </script>
    @endpush
</x-layout>
