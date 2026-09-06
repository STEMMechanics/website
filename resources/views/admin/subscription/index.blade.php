<x-layout>
    <x-mast>Email Subscriptions
        <x-slot:actions><x-ui.button color="mast" href="{{ route('admin.subscription.create') }}">Register</x-ui.button></x-slot:actions>
    </x-mast>

    <x-container class="py-5 sm:py-8">
        <x-ui.dynamic-list name="admin-subscription-index">

        <x-ui.collection-controls class="my-5" />
        @if($subscriptions->isEmpty())
            <x-none-found item="subscriptions" search="{{ request()->get('search') }}" />
        @else
            <div data-list-results class="space-y-4 md:hidden">
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
                        <x-ui.row-actions class="mt-2.5">
                            @if($subscription->confirmed)
                                <form method="POST" action="{{ route('admin.subscription.send-now', $subscription) }}">
                                    @csrf
                                    <x-ui.row-action label="Send newsletter now" icon="fa-paper-plane" type="submit" />
                                </form>
                            @else
                                <x-ui.row-action label="Confirm subscription before sending" icon="fa-solid fa-paper-plane" tone="neutral" type="button" disabled />
                            @endif

                            <x-ui.row-action label="Edit" icon="fa-solid fa-pen-to-square" tone="primary" href="{{ route('admin.subscription.edit', $subscription) }}" />

                            <x-ui.row-action label="Delete" icon="fa-solid fa-trash" tone="danger" x-data x-on:click.prevent="SM.confirmDelete('{{ csrf_token() }}', 'Delete subscription?', 'Are you sure you want to delete this subscription? This action cannot be undone', '{{ route('admin.subscription.destroy', $subscription) }}')" />
                        </x-ui.row-actions>
                    </article>
                @endforeach
            </div>

            <div class="hidden md:block">
            <x-ui.table variant="listing">
                <x-slot:header>
                    <x-ui.list-heading label="Email" />
                    <x-ui.list-heading field="created_at" label="Registered On" />
                    <x-ui.list-heading label="Last Newsletter" />
                    <x-ui.list-heading class="text-center!" label="Actions" />
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
                                <x-ui.date-time>{{ $subscription->confirmed ? \Carbon\Carbon::parse($subscription->confirmed)->format('M j Y, g:i a') : '-' }}</x-ui.date-time>
                            </td>
                            <td>
                                @if($latestNewsletter === null)
                                    -
                                @else
                                    <x-ui.badge :color="$statusTone" size="xs">{{ $statusLabel }}</x-ui.badge>
                                    @if($statusTimestamp)
                                        <div class="mt-1 text-xs text-gray-500"><x-ui.date-time>{{ $statusTimestamp->format('M j Y, g:i a') }}</x-ui.date-time></div>
                                    @endif
                                    @if($newsletterStatus === \App\Models\SentEmail::STATUS_FAILED && ! empty($latestNewsletter->error_message))
                                        <div class="mt-1 max-w-xs truncate text-xs text-gray-500" title="{{ $latestNewsletter->error_message }}">
                                            {{ \Illuminate\Support\Str::limit($latestNewsletter->error_message, 80) }}
                                        </div>
                                    @endif
                                @endif
                            </td>
                            <td class="text-center!">
                                <x-ui.row-actions>
                                    @if($subscription->confirmed)
                                        <form method="POST" action="{{ route('admin.subscription.send-now', $subscription) }}">
                                            @csrf
                                            <x-ui.row-action label="Send newsletter now" icon="fa-solid fa-paper-plane" tone="neutral" type="submit" />
                                        </form>
                                    @else
                                        <x-ui.row-action label="Confirm subscription before sending" icon="fa-paper-plane" disabled />
                                    @endif
                                    <x-ui.row-action label="Edit" icon="fa-solid fa-pen-to-square" tone="primary" href="{{ route('admin.subscription.edit', $subscription) }}" />
                                    <x-ui.row-action label="Delete" icon="fa-solid fa-trash" tone="danger" x-data x-on:click.prevent="SM.confirmDelete('{{ csrf_token() }}', 'Delete subscription?', 'Are you sure you want to delete this subscription? This action cannot be undone', '{{ route('admin.subscription.destroy', $subscription) }}')" />
                                </x-ui.row-actions>
                            </td>
                        </tr>
                    @endforeach
                </x-slot:body>
            </x-ui.table>
            </div>

            <x-ui.list-pagination :paginator="$subscriptions" />
        @endif

        </x-ui.dynamic-list>
    </x-container>
</x-layout>
