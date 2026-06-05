<x-layout>
    <x-mast>Email Subscriptions</x-mast>

    <x-container>
        <x-ui.toolbar>
            <x-slot:left>
                <div class="flex flex-col sm:flex-row items-center gap-2">
                    <x-ui.button href="{{ route('admin.subscription.create') }}" class="w-full sm:w-auto">Register</x-ui.button>
                    <form class="w-full" method="POST" action="{{ route('admin.subscription.send-all-now') }}" x-data x-on:submit.prevent="SM.confirm('Queue newsletter?', 'Queue newsletter for all confirmed subscriptions now?', 'Queue Newsletter', (isConfirmed) => { if (isConfirmed) { $el.submit(); } })">
                        @csrf
                        <x-ui.button type="submit" color="outline" class="w-full sm:w-auto">Send All Now</x-ui.button>
                    </form>
                </div>
            </x-slot:left>
            <x-slot:right>
                <x-ui.search name="search" label="Search" />
            </x-slot:right>
        </x-ui.toolbar>

        <div class="mb-6 rounded-lg border border-gray-200 bg-white p-4">
            <form method="POST" action="{{ route('admin.subscription.send-test-now') }}" class="flex flex-col gap-4 md:flex-row items-center">
                @csrf
                <div class="w-full md:max-w-lg">
                    <x-ui.input
                        class="mb-0"
                        label="Send test newsletter to email"
                        name="test_email"
                        type="email"
                        value="{{ old('test_email') }}"
                        info="Queues the existing newsletter to this address without creating or updating a subscription."
                    />
                </div>
                <x-ui.button type="submit" color="outline" class="mb-3 w-full sm:w-auto">Send Test Email</x-ui.button>
            </form>
        </div>

        @if($subscriptions->isEmpty())
            <x-none-found item="subscriptions" search="{{ request()->get('search') }}" />
        @else
            <div class="space-y-4 md:hidden">
                @foreach ($subscriptions as $subscription)
                    @php
                        $latestNewsletter = $latestNewsletterByEmail->get(strtolower(trim((string) $subscription->email)));
                        $newsletterStatus = (string) ($latestNewsletter->status ?? '');
                        $statusTimestamp = $newsletterStatus === \App\Models\SentEmail::STATUS_SENT
                            ? ($latestNewsletter->sent_at ?? $latestNewsletter->created_at)
                            : ($newsletterStatus === \App\Models\SentEmail::STATUS_FAILED
                                ? ($latestNewsletter->failed_at ?? $latestNewsletter->created_at)
                                : $latestNewsletter?->created_at);
                        $statusLabel = $newsletterStatus === \App\Models\SentEmail::STATUS_FAILED
                            ? 'Failed'
                            : ($newsletterStatus === \App\Models\SentEmail::STATUS_SENT ? 'Sent' : 'Queued');
                        $statusTone = \App\Models\SentEmail::statusBadgeToneFor($newsletterStatus);
                    @endphp

                    <article class="rounded-2xl border border-gray-200 bg-white p-3 shadow-sm">
                        <div class="min-w-0">
                            <div class="break-all text-sm font-semibold leading-5 text-gray-900">{{ $subscription->email }}</div>
                            <div class="mt-0.5 flex flex-wrap items-center gap-x-1.5 gap-y-0.5 text-xs text-gray-500">
                                <span>{{ $subscription->confirmed ? 'Registered '.\Carbon\Carbon::parse($subscription->confirmed)->format('j M Y') : 'Not confirmed yet' }}</span>
                                @if($subscription->confirmed)
                                    <span class="text-gray-300">•</span>
                                    <span class="font-medium text-gray-700">{{ \Carbon\Carbon::parse($subscription->confirmed)->format('g:i a') }}</span>
                                @endif
                            </div>
                            <div class="mt-2 flex flex-row items-center gap-2">
                                <div class="text-xs font-semibold text-gray-500">Last newsletter: </div>
                                @if($latestNewsletter === null)
                                    <div class="mt-0.5 text-xs text-gray-700">No newsletter sent yet</div>
                                @else
                                    @if($statusTimestamp)
                                        <div class="mt-0.5 text-xs text-gray-500">{{ $statusTimestamp->format('M j Y, g:i a') }}</div>
                                    @endif
                                    <x-ui.badge :color="$statusTone" size="xs">{{ $statusLabel }}</x-ui.badge>
                                    @if($newsletterStatus === \App\Models\SentEmail::STATUS_FAILED && ! empty($latestNewsletter->error_message))
                                        <div class="mt-1 text-xs text-gray-500">
                                            {{ \Illuminate\Support\Str::limit($latestNewsletter->error_message, 120) }}
                                        </div>
                                    @endif
                                @endif
                            </div>
                        </div>
                        <div class="mt-2.5 grid grid-cols-3 gap-1.5 sm:grid-cols-3">
                            @if($subscription->confirmed)
                                <form method="POST" action="{{ route('admin.subscription.send-now', $subscription) }}">
                                    @csrf
                                    <button type="submit" class="inline-flex w-full items-center justify-center rounded-md border border-primary-color bg-white px-2 py-2.5 text-xs font-semibold text-primary-color transition hover:bg-primary-color hover:text-white" title="Send newsletter now">
                                        <i class="fa-solid fa-paper-plane"></i>
                                    </button>
                                </form>
                            @else
                                <button type="button" class="inline-flex w-full cursor-not-allowed items-center justify-center rounded-md border border-gray-200 bg-gray-100 px-2 py-2.5 text-xs font-semibold text-gray-400" disabled title="Confirm subscription before sending">
                                    <i class="fa-solid fa-paper-plane"></i>
                                </button>
                            @endif

                            <a href="{{ route('admin.subscription.edit', $subscription) }}" class="inline-flex w-full items-center justify-center rounded-md border border-gray-300 bg-white px-2 py-2.5 text-xs font-semibold text-gray-700 transition hover:border-primary-color hover:text-primary-color" title="Edit">
                                <i class="fa-solid fa-pen-to-square"></i>
                            </a>

                            <a href="#" class="inline-flex w-full items-center justify-center rounded-md border border-red-200 bg-red-50 px-2 py-2.5 text-xs font-semibold text-red-700 transition hover:bg-red-600 hover:text-white" title="Delete" x-data x-on:click.prevent="SM.confirmDelete('{{ csrf_token() }}', 'Delete subscription?', 'Are you sure you want to delete this subscription? This action cannot be undone', '{{ route('admin.subscription.destroy', $subscription) }}')">
                                <i class="fa-solid fa-trash"></i>
                            </a>
                        </div>
                    </article>
                @endforeach
            </div>

            <div class="hidden md:block">
            <x-ui.table>
                <x-slot:header>
                    <th>Email</th>
                    <th>Registered On</th>
                    <th>Last Newsletter</th>
                    <th>Action</th>
                </x-slot:header>
                <x-slot:body>
                    @foreach ($subscriptions as $subscription)
                        @php
                            $latestNewsletter = $latestNewsletterByEmail->get(strtolower(trim((string) $subscription->email)));
                            $newsletterStatus = (string) ($latestNewsletter->status ?? '');
                            $statusTimestamp = $newsletterStatus === \App\Models\SentEmail::STATUS_SENT
                                ? ($latestNewsletter->sent_at ?? $latestNewsletter->created_at)
                                : ($newsletterStatus === \App\Models\SentEmail::STATUS_FAILED
                                    ? ($latestNewsletter->failed_at ?? $latestNewsletter->created_at)
                                    : $latestNewsletter?->created_at);
                            $statusLabel = $newsletterStatus === \App\Models\SentEmail::STATUS_FAILED
                                ? 'Failed'
                                : ($newsletterStatus === \App\Models\SentEmail::STATUS_SENT ? 'Sent' : 'Queued');
                            $statusClass = $newsletterStatus === \App\Models\SentEmail::STATUS_FAILED
                                ? 'text-red-700 bg-red-100 border-red-200'
                                : ($newsletterStatus === \App\Models\SentEmail::STATUS_SENT
                                    ? 'text-green-700 bg-green-100 border-green-200'
                                    : 'text-amber-700 bg-amber-100 border-amber-200');
                        @endphp
                        <tr>
                            <td>
                                <div class="whitespace-normal">{{ $subscription->email }}</div>
                            </td>
                            <td>
                                {{ $subscription->confirmed ? \Carbon\Carbon::parse($subscription->confirmed)->format('M j Y, g:i a') : '-' }}
                            </td>
                            <td>
                                @if($latestNewsletter === null)
                                    -
                                @else
                                    <x-ui.badge :color="$statusTone" size="xs">{{ $statusLabel }}</x-ui.badge>
                                    @if($statusTimestamp)
                                        <div class="mt-1 text-xs text-gray-500">{{ $statusTimestamp->format('M j Y, g:i a') }}</div>
                                    @endif
                                    @if($newsletterStatus === \App\Models\SentEmail::STATUS_FAILED && ! empty($latestNewsletter->error_message))
                                        <div class="mt-1 max-w-xs truncate text-xs text-gray-500" title="{{ $latestNewsletter->error_message }}">
                                            {{ \Illuminate\Support\Str::limit($latestNewsletter->error_message, 80) }}
                                        </div>
                                    @endif
                                @endif
                            </td>
                            <td>
                                <div class="flex justify-center gap-3">
                                    @if($subscription->confirmed)
                                        <form method="POST" action="{{ route('admin.subscription.send-now', $subscription) }}">
                                            @csrf
                                            <button type="submit" class="hover:text-primary-color" title="Send newsletter now">
                                                <i class="fa-solid fa-paper-plane"></i>
                                            </button>
                                        </form>
                                    @else
                                        <span class="cursor-not-allowed text-gray-300" title="Confirm subscription before sending">
                                            <i class="fa-solid fa-paper-plane"></i>
                                        </span>
                                    @endif
                                    <a href="{{ route('admin.subscription.edit', $subscription) }}" class="hover:text-primary-color" title="Edit">
                                        <i class="fa-solid fa-pen-to-square"></i>
                                    </a>
                                    <a href="#" class="hover:text-red-600" x-data x-on:click.prevent="SM.confirmDelete('{{ csrf_token() }}', 'Delete subscription?', 'Are you sure you want to delete this subscription? This action cannot be undone', '{{ route('admin.subscription.destroy', $subscription) }}')">
                                        <i class="fa-solid fa-trash"></i>
                                    </a>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </x-slot:body>
            </x-ui.table>
            </div>

            {{ $subscriptions->appends(request()->query())->links() }}
        @endif
    </x-container>
</x-layout>
