<x-layout>
    <x-mast>Reminders</x-mast>
    <x-container>
        <x-ui.dynamic-list name="admin-reminder-index">

        <x-ui.collection-controls class="my-5" />

        @if($reminders->isEmpty())
            <x-none-found item="reminders" search="{{ request('search') }}" />
        @else
            <x-ui.table variant="listing">
                <x-slot:header>
                    <th class="text-center! w-12"><x-ui.checkbox bare small data-reminder-select-all aria-label="Select reminders" /></th>
                    <x-ui.list-heading field="subject" label="Reminder" /><x-ui.list-heading class="hidden md:table-cell" label="Recipient" /><x-ui.list-heading field="scheduled_at" class="text-center!" label="Scheduled / Sent" /><x-ui.list-heading class="text-center!" label="Status" /><x-ui.list-heading class="text-center!" label="Actions" />
                </x-slot:header>
                <x-slot:body>
                    @foreach($reminders as $reminder)
                        <tr>
                            <td class="text-center!"><x-ui.checkbox bare small data-reminder-select value="{{ $reminder->id }}" aria-label="Select reminder {{ $reminder->id }}" /></td>
                            <td>
                                @php
                                    $isWorkshopTask = \Illuminate\Support\Str::startsWith(strtolower((string) $reminder->subject), 'workshop task:');
                                    $reminderTitle = $isWorkshopTask ? trim(\Illuminate\Support\Str::after((string) $reminder->subject, ':')) : (string) $reminder->subject;
                                @endphp
                                @if($reminder->action_url)
                                    <a href="{{ $reminder->action_url }}" class="hover:text-primary-color">
                                        @if($isWorkshopTask)<span class="font-semibold">Workshop Task:</span> {{ $reminderTitle }}@else<span class="font-semibold">{{ $reminderTitle }}</span>@endif
                                    </a>
                                @else
                                    @if($isWorkshopTask)<span class="font-semibold">Workshop Task:</span> {{ $reminderTitle }}@else<span class="font-semibold">{{ $reminderTitle }}</span>@endif
                                @endif
                                @if($reminder->remindable instanceof \App\Models\Workshop)
                                    <div class="mt-1 text-xs text-gray-500">
                                        {{ $reminder->remindable->title }}
                                        @if($reminder->remindable->starts_at) · <x-ui.date-time>{{ $reminder->remindable->starts_at->format('D j M Y, g:ia') }}</x-ui.date-time>@endif
                                        @if($reminder->remindable->getLocationName() !== '') · {{ $reminder->remindable->getLocationName() }}@endif
                                    </div>
                                @endif
                                <div class="mt-1 text-xs text-gray-500 md:hidden">{{ $reminder->recipient?->getName() ?: $reminder->recipient_email }} · {{ $reminder->recipient_email }}</div>
                            </td>
                            <td class="hidden md:table-cell"><div>{{ $reminder->recipient?->getName() ?: 'Unknown user' }}</div><div class="text-xs text-gray-500">{{ $reminder->recipient_email }}</div></td>
                            <td class="text-center!"><div><x-ui.date-time>{{ $reminder->scheduled_at?->format('D j M Y, g:ia') }}</x-ui.date-time></div>@if($reminder->sent_at)<div class="text-xs text-gray-500">Sent <x-ui.date-time>{{ $reminder->sent_at->format('D j M Y, g:ia') }}</x-ui.date-time></div>@endif</td>
                            <td class="text-center!">
                                @php
                                    $statusTone = match ($reminder->status) {
                                        \App\Models\Reminder::STATUS_PENDING => 'warning',
                                        \App\Models\Reminder::STATUS_QUEUED => 'sky',
                                        \App\Models\Reminder::STATUS_SENT => 'success',
                                        \App\Models\Reminder::STATUS_FAILED => 'danger',
                                        default => 'gray',
                                    };
                                @endphp
                                <x-ui.badge :color="$statusTone" size="xs">{{ ucfirst($reminder->status) }}</x-ui.badge>
                                @if($reminder->failure_message)<div class="mx-auto mt-1 max-w-xs text-xs text-red-600">{{ $reminder->failure_message }}</div>@endif
                            </td>
                            <td class="text-center! whitespace-nowrap">
                                <x-ui.action-menu id="reminder-actions-{{ $reminder->id }}" title="Reminder actions">
                                @if(in_array($reminder->status, [\App\Models\Reminder::STATUS_PENDING, \App\Models\Reminder::STATUS_QUEUED, \App\Models\Reminder::STATUS_FAILED, \App\Models\Reminder::STATUS_SENT], true))
                                    @php
                                        $sendActionLabel = match ($reminder->status) {
                                            \App\Models\Reminder::STATUS_PENDING => 'Send Now',
                                            \App\Models\Reminder::STATUS_SENT => 'Resend',
                                            default => 'Retry Now',
                                        };
                                    @endphp
                                    <form method="POST" action="{{ route('admin.reminder.send-now', $reminder) }}"  data-sm-confirm="{{ $sendActionLabel }} this reminder to {{ $reminder->recipient_email }}?" data-sm-confirm-button="{{ $sendActionLabel }}">
                                        @csrf
                                        <x-ui.row-action type="submit" :label="$sendActionLabel" icon="fa-paper-plane" tone="primary" />
                                    </form>
                                @endif
                                    @if($reminder->status === \App\Models\Reminder::STATUS_CANCELLED)<p class="p-3 text-sm text-slate-500">Use bulk edit to requeue this reminder.</p>@endif
                                </x-ui.action-menu>
                            </td>
                        </tr>
                    @endforeach
                </x-slot:body>
            </x-ui.table>
            <x-ui.list-pagination :paginator="$reminders">
                <x-slot:actions>
                    <form method="POST" action="{{ route('admin.reminder.bulk.edit') }}" data-bulk-open="reminder-bulk-dialog" data-reminder-bulk-form>
                        @csrf
                        <div data-reminder-bulk-inputs></div>
                        <x-ui.bulk-edit-button type="submit" :count="0" data-reminder-edit disabled hidden />
                    </form>
                </x-slot:actions>
            </x-ui.list-pagination>
        @endif

        </x-ui.dynamic-list>
        <x-ui.bulk-editor id="reminder-bulk-dialog" title="Edit reminders" loader-id="reminder-bulk-loader" list="admin-reminder-index" selection-key="admin-reminder-selection" selection-field="reminder_ids[]" />
    </x-container>
</x-layout>
