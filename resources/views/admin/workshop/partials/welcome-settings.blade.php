<div class="my-6" x-show="registration === 'tickets'" x-data="{
    welcomeEnabled: @js((bool) old('welcome_enabled', $workshopModel?->welcome_enabled ?? false)),
    welcomeFiles: @js(old('welcome_files', $workshopModel?->files('welcome_attachments')->pluck('name')->all() ?? [])),
    pickWelcomeFiles() {
        SMMediaPicker.open(this.welcomeFiles, { allow_multiple: true, allow_uploads: true, public_usable_only: false, title: 'Welcome email attachments' }, result => {
            this.welcomeFiles = [...new Set((Array.isArray(result) ? result : [result]).filter(Boolean))];
        });
    }
}">
    <x-ui.collapsible-section title="Welcome email" variant="product" :open="$errors->hasAny(['welcome_subject', 'welcome_body', 'welcome_send_at', 'welcome_files'])">
        <x-slot:summary><span x-text="welcomeEnabled ? 'Scheduled joining instructions' : 'Not enabled'"></span></x-slot:summary>
        <input type="hidden" name="welcome_enabled" x-bind:value="welcomeEnabled ? 1 : 0">
        <x-ui.checkbox label="Send welcome email to active ticket contacts" x-model="welcomeEnabled" />
        <div x-show="welcomeEnabled" class="mt-4">
            <x-ui.input label="Subject" name="welcome_subject" :value="old('welcome_subject', $workshopModel?->welcome_subject ?? 'Welcome to '.($workshopModel?->title ?? 'your workshop'))" :error="$errors->first('welcome_subject')" />
            <x-ui.editor label="Joining instructions" name="welcome_body" :value="old('welcome_body', $workshopModel?->welcome_body ?? '')" />
            @error('welcome_body')<p class="text-sm text-red-600">{{ $message }}</p>@enderror
            <x-ui.input type="datetime-local" label="Send at" name="welcome_send_at" :value="old('welcome_send_at', $workshopModel?->welcome_send_at?->format('Y-m-d\TH:i') ?? '')" info="Leave blank for 3 days before the first session. Later bookings receive it when checkout completes, until the course starts." :error="$errors->first('welcome_send_at')" />
            <x-ui.button type="button" color="outline" x-on:click="pickWelcomeFiles()">Select or upload attachments</x-ui.button>
            <template x-for="(file, index) in welcomeFiles" :key="file">
                <div class="mt-2 flex items-center gap-3 text-sm">
                    <input type="hidden" name="welcome_files[]" x-bind:value="file">
                    <span x-text="file"></span>
                    <x-ui.button variant="plain" type="button" aria-label="Remove welcome attachment" x-on:click="welcomeFiles.splice(index, 1)"><i class="fa-solid fa-xmark" aria-hidden="true"></i></x-ui.button>
                </div>
            </template>
            @if($workshopModel?->exists)
                @php
                    $welcomeCounts = \Illuminate\Support\Facades\DB::table('workshop_welcome_deliveries')->where('workshop_id', $workshopModel->id)->where('generation', $workshopModel->welcome_generation)->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status');
                @endphp
                <div class="mt-5 flex flex-wrap gap-2">
                    @if($workshopModel->welcome_enabled && $workshopModel->welcome_send_at)
                        <x-ui.badge color="info">{{ $workshopModel->welcome_send_at->isFuture() ? 'Scheduled '.$workshopModel->welcome_send_at->format('j M Y g:ia') : ($workshopModel->effectiveStartsAt()?->isFuture() ? 'Sending to new active bookings' : 'Delivery window ended') }}</x-ui.badge>
                    @endif
                    @foreach($welcomeCounts as $status => $count)
                        <x-ui.badge :color="$status === 'failed' ? 'danger' : ($status === 'sent' ? 'success' : 'info')">{{ $count }} {{ $status }}</x-ui.badge>
                    @endforeach
                </div>
                <p class="mt-3 text-xs text-gray-500">These actions use the saved email. Save changes first. Editing content does not resend it.</p>
                <div class="mt-3 flex flex-wrap gap-3">
                    <x-ui.button type="button" color="outline" onclick="window.open(@js(route('admin.workshop.welcome.preview', $workshopModel)), '_blank', 'noopener')">Preview saved email</x-ui.button>
                    <x-ui.button type="submit" form="send-workshop-welcome" name="action" value="send">Send now</x-ui.button>
                    @if($welcomeCounts->get('sent', 0))
                        <x-ui.button type="submit" form="send-workshop-welcome" name="action" value="resend" x-on:click.prevent="const button = $el; const result = await SM.confirm('Resend welcome email?', 'The saved email will be sent again to all active ticket contacts.', 'Resend'); if (result?.isConfirmed) button.form.requestSubmit(button)" color="outline">Resend to active contacts</x-ui.button>
                    @endif
                    @if($welcomeCounts->get('failed', 0))
                        <x-ui.button type="submit" form="send-workshop-welcome" name="action" value="retry" color="outline">Retry failed emails</x-ui.button>
                    @endif
                </div>
            @endif
        </div>
    </x-ui.collapsible-section>
</div>
