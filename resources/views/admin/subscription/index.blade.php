<x-layout>
    <x-mast>Email Subscriptions</x-mast>

    <x-container>
        <x-ui.toolbar>
            <x-slot:left>
                <div class="flex items-center gap-2">
                    <x-ui.button href="{{ route('admin.subscription.create') }}">Register</x-ui.button>
                    <form method="POST" action="{{ route('admin.subscription.send-all-now') }}" x-data x-on:submit.prevent="SM.confirm('Queue newsletter?', 'Queue newsletter for all confirmed subscriptions now?', 'Queue Newsletter', (isConfirmed) => { if (isConfirmed) { $el.submit(); } })">
                        @csrf
                        <x-ui.button type="submit" color="outline">Send All Now</x-ui.button>
                    </form>
                </div>
            </x-slot:left>
            <x-slot:right>
                <x-ui.search name="search" label="Search" />
            </x-slot:right>
        </x-ui.toolbar>

        <div class="mb-6 rounded-lg border border-gray-200 bg-white p-4">
            <form method="POST" action="{{ route('admin.subscription.send-test-now') }}" class="flex flex-col gap-4 md:flex-row md:items-end">
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
                <x-ui.button type="submit" color="outline">Send Test Email</x-ui.button>
            </form>
        </div>

        @if($subscriptions->isEmpty())
            <x-none-found item="subscriptions" search="{{ request()->get('search') }}" />
        @else
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
                                    <div class="{{ $newsletterStatus === \App\Models\SentEmail::STATUS_FAILED ? 'text-red-600' : ($newsletterStatus === \App\Models\SentEmail::STATUS_SENT ? 'text-green-700' : 'text-amber-700') }}">
                                        {{ $newsletterStatus === \App\Models\SentEmail::STATUS_FAILED ? 'Failed' : ($newsletterStatus === \App\Models\SentEmail::STATUS_SENT ? 'Sent' : 'Queued') }}
                                    </div>
                                    @if($statusTimestamp)
                                        <div class="text-xs text-gray-500">{{ $statusTimestamp->format('M j Y, g:i a') }}</div>
                                    @endif
                                    @if($newsletterStatus === \App\Models\SentEmail::STATUS_FAILED && ! empty($latestNewsletter->error_message))
                                        <div class="text-xs text-gray-500 max-w-xs truncate" title="{{ $latestNewsletter->error_message }}">
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
                                        <span class="text-gray-300 cursor-not-allowed" title="Confirm subscription before sending">
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

            {{ $subscriptions->appends(request()->query())->links() }}
        @endif
    </x-container>
</x-layout>
