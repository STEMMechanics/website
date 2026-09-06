<x-layout>
    <x-mast backRoute="admin.subscription.index" backTitle="Email Subscriptions">{{ isset($subscription) ? 'Edit' : 'Create' }} Subscription</x-mast>

    <x-container class="mt-4">
        <form method="POST" action="{{ route('admin.subscription.' . ( isset($subscription) ? 'update' : 'store'), $subscription ?? []) }}">
            @isset($subscription)
            @method('PUT')
            @endisset
            @csrf

            <div class="mb-4">
                <x-ui.input label="Email" name="email" type="email" value="{{ $subscription->email ?? '' }}" />
            </div>
            <div class="mb-4">
                <x-ui.input label="Registered On" name="confirmed" value="{{ $subscription->confirmed ? \Carbon\Carbon::parse($subscription->confirmed)->format('M j Y, g:i a') : '-' }}" disabled />
            </div>
            <x-ui.editor-actions>
                @isset($subscription)
                <x-ui.button data-editor-delete type="button" color="danger" x-data x-on:click.prevent="SM.confirmDelete('{{ csrf_token() }}', 'Delete subscription?', 'Are you sure you want to delete this subscription? This action cannot be undone', '{{ route('admin.subscription.destroy', $subscription) }}')">Delete</x-ui.button>
                @endisset
                <x-ui.button type="submit">Save</x-ui.button>
            </x-ui.editor-actions>
        </form>
    </x-container>
</x-layout>
