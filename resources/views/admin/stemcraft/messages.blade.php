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
                <h2 class="text-xl font-semibold text-gray-900">Player messaging</h2>
                <p class="mt-2 text-sm leading-6 text-gray-600">Review chat, books, signs, and other player messages recorded by the STEMCraft webhook, including the displayed filtered text, the raw text for admins, and the location where the message was created.</p>
            </div>

            <form method="GET" action="{{ route('admin.stemcraft.messages.index') }}" class="mt-6 grid gap-4 lg:grid-cols-4 items-center">
                <x-ui.input name="search" label="Search" value="{{ $search }}" />
                <x-ui.select name="message_type" label="Message type">
                    <option value="">All types</option>
                    @foreach($messageTypes as $messageType)
                        <option value="{{ $messageType }}" {{ $selectedMessageType === $messageType ? 'selected' : '' }}>{{ ucfirst(str_replace('_', ' ', $messageType)) }}</option>
                    @endforeach
                </x-ui.select>
                <x-ui.select name="status" label="Moderation result">
                    <option value="">All messages</option>
                    <option value="passed" {{ $selectedStatus === 'passed' ? 'selected' : '' }}>Passed</option>
                    <option value="blocked" {{ $selectedStatus === 'blocked' ? 'selected' : '' }}>Blocked</option>
                </x-ui.select>
                <x-ui.button type="submit" class="mt-1">Filter</x-ui.button>
            </form>
        </section>

        <div id="stemcraft-messages-results">
            @include('admin.stemcraft.partials.messages-results', [
                'messages' => $messages,
            ])
        </div>
    </x-container>

    @push('scripts')
        <script>
            document.addEventListener('DOMContentLoaded', () => {
                const results = document.getElementById('stemcraft-messages-results');
                const snapshotUrl = @js(route('admin.stemcraft.messages.snapshot', request()->query()));

                if (!results || !snapshotUrl) {
                    return;
                }

                const buildSnapshotUrl = () => {
                    const url = new URL(snapshotUrl, window.location.origin);
                    url.searchParams.set('_refresh', Date.now().toString());

                    return url.toString();
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
                        const replaced = SM.replaceHtmlPreservingState(results, payload?.resultsHtml || '');

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
