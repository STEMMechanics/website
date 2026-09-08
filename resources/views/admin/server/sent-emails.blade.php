<x-layout>
    <x-mast>Sent Emails</x-mast>

    <x-container>
        <x-ui.dynamic-list name="admin-server-sent-emails">

        <div class="my-4">
            <x-ui.collection-controls />
            <div class="mt-2 text-xs text-gray-500">
                Search by recipient, template class, error text, or record ID.
            </div>
        </div>

        @if($emails->isEmpty())
            <x-none-found item="sent emails" search="{{ request()->get('search') }}" />
        @else
            <x-ui.table variant="listing">
                <x-slot:header>
                    <th>
                        <span class="md:hidden">Email</span>
                        <span class="hidden md:inline">Created</span>
                    </th>
                    <x-ui.list-heading field="mailable_class" class="hidden md:table-cell" label="Details" />
                    <x-ui.list-heading class="hidden md:table-cell text-center!" label="Sent" />
                    <x-ui.list-heading class="hidden md:table-cell" label="Record ID" />
                    <x-ui.list-heading class="text-center!" label="Status" />
                </x-slot:header>
                <x-slot:body>
                    @foreach($emails as $email)
                        @php
                            $status = $email->status ?? 'sent';
                            $statusTone = match ($status) {
                                'failed' => 'danger',
                                'skipped' => 'slate',
                                'scheduled' => 'sky',
                                'queued' => 'warning',
                                default => 'success',
                            };
                        @endphp
                        <tr>
                            <td>
                                <div class="text-xs sm:text-sm"><x-ui.date-time>{{ $email->created_at?->format('M j, Y g:i a') ?? '-' }}</x-ui.date-time></div>
                                <div class="md:hidden mt-1">{{ $email->recipient }}</div>
                                <div class="md:hidden text-xs font-medium">{{ class_basename($email->mailable_class) }}</div>
                                @if($email->scheduled_for_at)
                                    <div class="md:hidden text-xs text-sky-700 mt-1">Scheduled for <x-ui.date-time>{{ $email->scheduled_for_at->format('M j, Y g:i a') }}</x-ui.date-time></div>
                                @endif
                                @if($status === \App\Models\SentEmail::STATUS_SKIPPED)
                                    <div class="md:hidden text-xs text-slate-700 mt-1">Skipped because the email was already sent.</div>
                                @endif
                                <div class="md:hidden text-xs text-gray-500 break-all">{{ $email->mailable_class }}</div>
                                <div class="md:hidden text-xs text-gray-600 mt-1">ID: <span class="font-mono">{{ $email->id }}</span></div>
                                </td>
                            <td class="hidden md:table-cell">
                                <div>{{ $email->recipient }}</div>
                                <div class="font-medium">{{ class_basename($email->mailable_class) }}</div>
                                @if($email->scheduled_for_at)
                                    <div class="text-xs text-sky-700">Scheduled for <x-ui.date-time>{{ $email->scheduled_for_at->format('M j, Y g:i a') }}</x-ui.date-time></div>
                                @endif
                                @if($status === \App\Models\SentEmail::STATUS_SKIPPED)
                                    <div class="text-xs text-slate-700">Skipped because the email was already sent.</div>
                                @endif
                                <div class="text-xs text-gray-500 break-all">{{ $email->mailable_class }}</div>
                            </td>
                            <td class="hidden md:table-cell text-center!"><x-ui.date-time>{{ $email->sent_at?->format('M j, Y g:i a') ?? '-' }}</x-ui.date-time></td>
                            <td class="hidden md:table-cell text-xs font-mono">{{ $email->id }}</td>
                            <td class="text-center! whitespace-nowrap">
                                <x-ui.badge :color="$statusTone" class="text-center">{{ ucfirst($status) }}</x-ui.badge>
                                @if($status === 'failed' || $email->failed_at || $email->error_message)
                                    <x-ui.button variant="plain" data-open-dialog="email-failure-{{ $email->id }}" aria-haspopup="dialog" aria-controls="email-failure-{{ $email->id }}" aria-label="View failure details for email {{ $email->id }}" class="inline-flex h-8 w-8 items-center justify-center p-0! text-slate-500 hover:text-red-700">
                                        <i class="fa-solid fa-circle-info" aria-hidden="true"></i>
                                    </x-ui.button>
                                    <x-ui.list-dialog id="email-failure-{{ $email->id }}" title="Email failure details">
                                        <dl class="space-y-4 p-5 text-left whitespace-normal">
                                            <div><dt class="font-semibold">Failed at</dt><dd>{{ $email->failed_at?->format('M j, Y g:i a') ?? 'Not recorded' }}</dd></div>
                                            <div><dt class="font-semibold">Error</dt><dd class="whitespace-pre-wrap wrap-break-word text-sm text-red-700">{{ $email->error_message ?: 'No error details recorded.' }}</dd></div>
                                        </dl>
                                    </x-ui.list-dialog>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </x-slot:body>
            </x-ui.table>

            <x-ui.list-pagination :paginator="$emails" />
        @endif

        </x-ui.dynamic-list>
    </x-container>
</x-layout>
