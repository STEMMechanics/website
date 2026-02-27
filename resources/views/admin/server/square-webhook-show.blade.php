<x-layout>
    <x-mast backRoute="admin.server.square-webhooks" backTitle="Square Webhooks">Webhook Event #{{ $event->id }}</x-mast>

    <x-container x-data="{
        ignoreOpen: false,
        reasonCode: '',
        reasonOther: '',
        openIgnore() {
            this.reasonCode = '';
            this.reasonOther = '';
            this.ignoreOpen = true;
        },
        closeIgnore() {
            this.ignoreOpen = false;
        },
        canSubmitIgnore() {
            if (!this.reasonCode) return false;
            if (this.reasonCode !== 'other') return true;
            return this.reasonOther.trim().length > 0;
        }
    }">
        @if($errors->any())
            <div class="my-4 rounded-lg border border-red-300 bg-red-50 p-3 text-sm text-red-800">
                {{ $errors->first() }}
            </div>
        @endif
        <div class="my-4 bg-white border border-gray-200 rounded-lg shadow-sm p-4">
            <h3 class="text-lg font-bold mb-3">Event Details</h3>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-x-8 gap-y-2 text-sm">
                <div class="py-1 border-b border-gray-100"><span class="font-semibold">ID:</span> {{ $event->id }}</div>
                <div class="py-1 border-b border-gray-100"><span class="font-semibold">Processed At:</span> {{ $event->processed_at?->format('M j, Y g:i a') ?? '-' }}</div>
                <div class="py-1 border-b border-gray-100"><span class="font-semibold">Event Type:</span> {{ $event->event_type ?: '-' }}</div>
                <div class="py-1 border-b border-gray-100"><span class="font-semibold">Event ID:</span> <span class="font-mono text-xs">{{ $event->event_id }}</span></div>
                <div class="py-1 border-b border-gray-100"><span class="font-semibold">Square Payment ID:</span> {{ $squarePaymentId !== '' ? $squarePaymentId : '-' }}</div>
                <div class="py-1 border-b border-gray-100">
                    <span class="font-semibold">Payment:</span>
                    @if($event->customerPayment)
                        <a href="{{ route('admin.payment.edit', $event->customerPayment) }}" class="text-primary-color hover:underline">#{{ $event->customerPayment->id }}</a>
                    @elseif($event->payment_id)
                        #{{ $event->payment_id }}
                    @else
                        -
                    @endif
                </div>
            </div>
        </div>

        <div class="my-4 bg-white border border-gray-200 rounded-lg shadow-sm p-4">
            <h3 class="text-lg font-bold mb-3">Ignore Handling</h3>
            @if($squarePaymentId === '')
                <div class="text-sm text-gray-600">This event has no payment ID, so an ignore rule cannot be created.</div>
            @elseif($ignoredRecord)
                <div class="text-sm mb-3 text-amber-700">
                    Ignored for sync. Saved {{ $ignoredRecord->created_at?->format('M j, Y g:i a') ?? '-' }}.
                </div>
                @if(trim((string) ($ignoredRecord->reason ?? '')) !== '')
                    <div class="text-sm mb-3"><span class="font-semibold">Reason:</span> {{ $ignoredRecord->reason }}</div>
                @endif
                <form method="POST" action="{{ route('admin.server.square-webhooks.unignore', $event) }}">
                    @csrf
                    @method('DELETE')
                    <x-ui.button type="submit" color="outline">Remove Ignore Rule</x-ui.button>
                </form>
            @else
                <x-ui.button type="button" color="outline" x-on:click.prevent="openIgnore()">Ignore This Square Payment</x-ui.button>
                <p class="text-xs text-gray-600 mt-2">Future webhook sync runs will skip auto-creating or linking this Square payment ID.</p>
            @endif
        </div>

        <div
            x-show="ignoreOpen"
            x-cloak
            class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4"
            x-on:keydown.escape.window="closeIgnore()">
            <div class="w-full max-w-lg rounded-lg border border-gray-200 bg-white p-4 shadow-xl">
                <div class="flex items-center justify-between mb-3">
                    <h3 class="text-lg font-bold">Ignore Square Payment</h3>
                    <button type="button" class="text-gray-500 hover:text-gray-700" x-on:click="closeIgnore()">
                        <i class="fa-solid fa-xmark"></i>
                    </button>
                </div>
                <p class="text-sm text-gray-700 mb-3">
                    This will prevent future syncs from creating/linking payment records for
                    <span class="font-mono">{{ $squarePaymentId }}</span>.
                </p>
                <form method="POST" action="{{ route('admin.server.square-webhooks.ignore', $event) }}">
                    @csrf
                    <div class="mb-4">
                        <label class="block text-sm pl-1">Reason</label>
                        <select name="reason_code" class="bg-white block mt-1 px-2.5 pt-2.5 pb-2.5 w-full text-sm text-gray-900 rounded-lg border border-gray-300" x-model="reasonCode">
                            <option value="" selected>Select reason</option>
                            @foreach(($ignoreReasonOptions ?? []) as $reasonKey => $reasonLabel)
                                <option value="{{ $reasonKey }}">{{ $reasonLabel }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="mb-4" x-show="reasonCode === 'other'" x-cloak>
                        <label class="block text-sm pl-1">Other Reason</label>
                        <textarea name="reason_other" x-model="reasonOther" rows="3" class="bg-white block mt-1 px-2.5 pt-2.5 pb-2.5 w-full text-sm text-gray-900 rounded-lg border border-gray-300"></textarea>
                    </div>
                    <div class="flex justify-end gap-3">
                        <button type="button"
                                class="border border-gray-300 bg-white text-gray-800 whitespace-nowrap text-center justify-center rounded-md px-6 py-2 text-sm font-semibold leading-6 shadow-sm hover:bg-gray-50 transition"
                                x-on:click.prevent="closeIgnore()">Cancel</button>
                        <button type="submit"
                                class="hover:bg-primary-color-dark focus-visible:outline-primary-color bg-primary-color text-white whitespace-nowrap text-center justify-center rounded-md px-8 py-2 text-sm font-semibold leading-6 shadow-sm focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 transition disabled:cursor-not-allowed disabled:opacity-50"
                                x-bind:disabled="!canSubmitIgnore()">
                            Ignore Payment
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <div class="my-4 bg-white border border-gray-200 rounded-lg shadow-sm p-4">
            <h3 class="text-lg font-bold mb-3">Payload</h3>
            <pre class="text-xs bg-gray-900 text-gray-100 rounded-md p-4 overflow-auto max-h-[38rem] whitespace-pre-wrap">{{ $payloadPretty ?: '{}' }}</pre>
        </div>
    </x-container>
</x-layout>
