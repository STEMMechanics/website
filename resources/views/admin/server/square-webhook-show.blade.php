<x-layout>
    <x-mast backRoute="admin.server.square-webhooks" backTitle="Square Webhooks">Webhook Event #{{ $event->id }}</x-mast>

    <x-container>
        <div class="my-4 bg-white border border-gray-200 rounded-lg shadow-sm p-4">
            <h3 class="text-lg font-bold mb-3">Event Details</h3>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-x-8 gap-y-2 text-sm">
                <div class="py-1 border-b border-gray-100"><span class="font-semibold">ID:</span> {{ $event->id }}</div>
                <div class="py-1 border-b border-gray-100"><span class="font-semibold">Processed At:</span> {{ $event->processed_at?->format('M j, Y g:i a') ?? '-' }}</div>
                <div class="py-1 border-b border-gray-100"><span class="font-semibold">Event Type:</span> {{ $event->event_type ?: '-' }}</div>
                <div class="py-1 border-b border-gray-100"><span class="font-semibold">Event ID:</span> <span class="font-mono text-xs">{{ $event->event_id }}</span></div>
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
            <h3 class="text-lg font-bold mb-3">Payload</h3>
            <pre class="text-xs bg-gray-900 text-gray-100 rounded-md p-4 overflow-auto max-h-[38rem] whitespace-pre-wrap">{{ $payloadPretty ?: '{}' }}</pre>
        </div>
    </x-container>
</x-layout>
