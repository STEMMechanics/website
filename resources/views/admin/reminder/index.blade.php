<x-layout>
    <x-mast>Reminders</x-mast>
    <x-container>
        <x-ui.toolbar>
            <x-slot:left>
                <div class="flex flex-wrap gap-2">
                    @foreach(['upcoming' => 'Upcoming', 'sent' => 'Sent', 'failed' => 'Failed', 'all' => 'All'] as $value => $label)
                        <x-ui.button href="{{ route('admin.reminder.index', ['view' => $value]) }}" :color="$selectedView === $value ? 'primary-outline' : 'outline'">{{ $label }}</x-ui.button>
                    @endforeach
                </div>
            </x-slot:left>
            <x-slot:right><x-ui.search name="search" label="Search" /></x-slot:right>
        </x-ui.toolbar>

        @if($reminders->isEmpty())
            <x-none-found item="reminders" search="{{ request('search') }}" />
        @else
            <x-ui.table>
                <x-slot:header>
                    <th>Reminder</th><th class="hidden md:table-cell">Recipient</th><th class="text-center">Scheduled / Sent</th><th class="text-center">Status</th>
                </x-slot:header>
                <x-slot:body>
                    @foreach($reminders as $reminder)
                        <tr>
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
                                        @if($reminder->remindable->starts_at) · {{ $reminder->remindable->starts_at->format('D j M Y, g:ia') }}@endif
                                        @if($reminder->remindable->getLocationName() !== '') · {{ $reminder->remindable->getLocationName() }}@endif
                                    </div>
                                @endif
                                <div class="mt-1 text-xs text-gray-500 md:hidden">{{ $reminder->recipient?->getName() ?: $reminder->recipient_email }} · {{ $reminder->recipient_email }}</div>
                            </td>
                            <td class="hidden md:table-cell"><div>{{ $reminder->recipient?->getName() ?: 'Unknown user' }}</div><div class="text-xs text-gray-500">{{ $reminder->recipient_email }}</div></td>
                            <td class="text-center"><div>{{ $reminder->scheduled_at?->format('D j M Y, g:ia') }}</div>@if($reminder->sent_at)<div class="text-xs text-gray-500">Sent {{ $reminder->sent_at->format('D j M Y, g:ia') }}</div>@endif</td>
                            <td class="text-center">
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
                                @if(in_array($reminder->status, [\App\Models\Reminder::STATUS_PENDING, \App\Models\Reminder::STATUS_QUEUED, \App\Models\Reminder::STATUS_FAILED, \App\Models\Reminder::STATUS_SENT], true))
                                    @php
                                        $sendActionLabel = match ($reminder->status) {
                                            \App\Models\Reminder::STATUS_PENDING => 'Send Now',
                                            \App\Models\Reminder::STATUS_SENT => 'Resend',
                                            default => 'Retry Now',
                                        };
                                    @endphp
                                    <form method="POST" action="{{ route('admin.reminder.send-now', $reminder) }}" class="mt-2" data-sm-confirm="{{ $sendActionLabel }} this reminder to {{ $reminder->recipient_email }}?" data-sm-confirm-button="{{ $sendActionLabel }}">
                                        @csrf
                                        <button type="submit" class="text-xs font-semibold text-primary-color hover:underline">{{ $sendActionLabel }}</button>
                                    </form>
                                @endif
                                @if($reminder->failure_message)<div class="mx-auto mt-1 max-w-xs text-xs text-red-600">{{ $reminder->failure_message }}</div>@endif
                            </td>
                        </tr>
                    @endforeach
                </x-slot:body>
            </x-ui.table>
            {{ $reminders->appends(request()->query())->links() }}
        @endif
    </x-container>
</x-layout>
