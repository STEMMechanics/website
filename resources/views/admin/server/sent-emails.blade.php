<x-layout>
    <x-mast>Sent Emails</x-mast>

    <x-container>
        <div class="my-4">
            <form method="GET" action="{{ route('admin.server.sent-emails') }}" class="w-full flex flex-col sm:flex-row items-end gap-3 sm:gap-4">
                <div class="w-full sm:w-64">
                    <x-ui.select label="Status" name="status">
                        <option value="">All statuses</option>
                        @foreach($statuses as $status)
                            <option value="{{ $status }}" {{ request('status') === $status ? 'selected' : '' }}>{{ ucfirst($status) }}</option>
                        @endforeach
                    </x-ui.select>
                </div>
                <div class="w-full sm:flex-1">
                    <x-ui.input name="search" label="Search" :value="request('search')" />
                </div>
                <div class="w-full sm:w-40 mb-4">
                    <x-ui.button type="submit" color="outline">Apply</x-ui.button>
                </div>
            </form>
            <div class="mt-2 text-xs text-gray-500">
                Search by recipient, template class, error text, or record ID.
            </div>
        </div>

        @if($emails->isEmpty())
            <x-none-found item="sent emails" search="{{ request()->get('search') }}" />
        @else
            <x-ui.table>
                <x-slot:header>
                    <th>
                        <span class="md:hidden">Email</span>
                        <span class="hidden md:inline">Created</span>
                    </th>
                    <th class="hidden md:table-cell">Details</th>
                    <th class="hidden md:table-cell">Sent</th>
                    <th class="hidden md:table-cell">Failed</th>
                    <th class="hidden lg:table-cell">Error</th>
                    <th class="hidden md:table-cell">Record ID</th>
                    <th>Status</th>
                </x-slot:header>
                <x-slot:body>
                    @foreach($emails as $email)
                        @php
                            $status = $email->status ?? 'sent';
                            $statusClass = match ($status) {
                                'failed' => 'text-red-700 bg-red-100 border-red-200',
                                'skipped' => 'text-slate-700 bg-slate-100 border-slate-200',
                                'scheduled' => 'text-sky-700 bg-sky-100 border-sky-200',
                                'queued' => 'text-amber-700 bg-amber-100 border-amber-200',
                                default => 'text-green-700 bg-green-100 border-green-200',
                            };
                        @endphp
                        <tr>
                            <td>
                                <div class="text-xs sm:text-sm">{{ $email->created_at?->format('M j, Y g:i a') ?? '-' }}</div>
                                <div class="md:hidden mt-1">{{ $email->recipient }}</div>
                                <div class="md:hidden text-xs font-medium">{{ class_basename($email->mailable_class) }}</div>
                                @if($email->scheduled_for_at)
                                    <div class="md:hidden text-xs text-sky-700 mt-1">Scheduled for {{ $email->scheduled_for_at->format('M j, Y g:i a') }}</div>
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
                                    <div class="text-xs text-sky-700">Scheduled for {{ $email->scheduled_for_at->format('M j, Y g:i a') }}</div>
                                @endif
                                @if($status === \App\Models\SentEmail::STATUS_SKIPPED)
                                    <div class="text-xs text-slate-700">Skipped because the email was already sent.</div>
                                @endif
                                <div class="text-xs text-gray-500 break-all">{{ $email->mailable_class }}</div>
                            </td>
                            <td class="hidden md:table-cell">{{ $email->sent_at?->format('M j, Y g:i a') ?? '-' }}</td>
                            <td class="hidden md:table-cell">{{ $email->failed_at?->format('M j, Y g:i a') ?? '-' }}</td>
                            <td class="hidden lg:table-cell text-xs text-red-700">{{ $email->error_message ?? '-' }}</td>
                            <td class="hidden md:table-cell text-xs font-mono">{{ $email->id }}</td>
                            <td>
                                <span class="inline-flex items-center rounded-full border px-2 py-0.5 text-xs font-semibold text-center {{ $statusClass }}">{{ ucfirst($status) }}</span>
                            </td>
                        </tr>
                    @endforeach
                </x-slot:body>
            </x-ui.table>

            {{ $emails->appends(request()->query())->links() }}
        @endif
    </x-container>
</x-layout>
