<form method="POST" action="{{ route('admin.reminder.bulk.update') }}" data-bulk-save>
    @csrf
    @method('PUT')
    @foreach($ids as $id)<input type="hidden" name="reminder_ids[]" value="{{ $id }}">@endforeach
    <div class="space-y-4 p-5">
        <p class="text-sm text-slate-600">Update {{ count($ids) }} selected {{ count($ids) === 1 ? 'reminder' : 'reminders' }}.</p>
        <x-ui.select name="action" label="Status" required class="mb-0">
            <option value="">Choose a change</option>
            <option value="cancel">Cancelled</option>
            <option value="requeue">Pending — requeue</option>
        </x-ui.select>
        <p class="text-sm text-slate-500">Requeued reminders keep a future scheduled time. Overdue reminders are scheduled for the next scheduler run. Previously sent reminders will be sent again.</p>
    </div>
    <div class="sm-dialog-footer"><x-ui.button color="outline" data-close-dialog>Cancel</x-ui.button><x-ui.button type="submit">Save changes</x-ui.button></div>
</form>
