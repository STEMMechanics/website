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
                <x-ui.checkbox label="Confirmed" name="confirmed" checked="{{ isset($subscription) && $subscription->confirmed ? true : false }}" />
            </div>

            <div class="flex justify-end mt-8 gap-4">
                @isset($subscription)
                    <x-ui.button type="button" color="danger" x-data x-on:click.prevent="SM.confirmDelete('{{ csrf_token() }}', 'Delete subscription?', 'Are you sure you want to delete this subscription? This action cannot be undone', '{{ route('admin.subscription.destroy', $subscription) }}')">Delete</x-ui.button>
                @endisset
                <x-ui.button type="submit">Save</x-ui.button>
            </div>
        </form>
    </x-container>
</x-layout>
